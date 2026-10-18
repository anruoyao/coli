"""Single-process, CPU-only NLLB service. Models and tokenizers are always local."""
from __future__ import annotations

import gc
import hmac
import json
import logging
import os
import threading
import time
from contextlib import asynccontextmanager
from pathlib import Path
from typing import Literal

import ctranslate2
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, ConfigDict, Field
from transformers import AutoTokenizer

logger = logging.getLogger(__name__)
MODEL_DIR = Path(os.getenv("NLLB_MODEL_DIR", "/opt/models/nllb-200-distilled-600M-ct2-int8")).resolve()
TOKENIZER_DIR = Path(os.getenv("NLLB_TOKENIZER_DIR", str(MODEL_DIR))).resolve()
CONFIG_FILE = Path(os.environ["NLLB_RUNTIME_CONFIG_FILE"]) if os.getenv("NLLB_RUNTIME_CONFIG_FILE") else None
API_KEY = os.getenv("NLLB_API_KEY", "")
LANGUAGES = {
    "en": "eng_Latn", "en-us": "eng_Latn", "en-gb": "eng_Latn",
    "zh": "zho_Hans", "zh-cn": "zho_Hans", "zh-hans": "zho_Hans",
    "zh-tw": "zho_Hant", "zh-hant": "zho_Hant", "ru": "rus_Cyrl",
    "ja": "jpn_Jpan", "ko": "kor_Hang", "fr": "fra_Latn", "de": "deu_Latn",
    "es": "spa_Latn", "pt": "por_Latn", "ar": "arb_Arab", "it": "ita_Latn",
    "tr": "tur_Latn", "pl": "pol_Latn", "uk": "ukr_Cyrl",
}


class RuntimeConfig(BaseModel):
    model_config = ConfigDict(extra="forbid", frozen=True, strict=True)
    threads: int = Field(default=2, ge=1, le=8)
    compute_type: Literal["int8", "int8_float32", "float32"] = "int8"
    beam_size: int = Field(default=2, ge=1, le=8)
    max_input_tokens: int = Field(default=512, ge=1, le=512)
    max_output_tokens: int = Field(default=512, ge=1, le=512)
    max_concurrent_requests: int = Field(default=1, ge=1, le=4)
    global_rate_per_minute: int = Field(default=10, ge=1, le=1200)


class RuntimeUpdate(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)
    threads: int | None = Field(default=None, ge=1, le=8)
    compute_type: Literal["int8", "int8_float32", "float32"] | None = None
    beam_size: int | None = Field(default=None, ge=1, le=8)
    max_input_tokens: int | None = Field(default=None, ge=1, le=512)
    max_output_tokens: int | None = Field(default=None, ge=1, le=512)
    max_concurrent_requests: int | None = Field(default=None, ge=1, le=4)
    global_rate_per_minute: int | None = Field(default=None, ge=1, le=1200)


class TranslationRequest(BaseModel):
    model_config = ConfigDict(extra="forbid")
    text: str = Field(min_length=1, max_length=10000)
    source: str = Field(min_length=2, max_length=12)
    target: str = Field(min_length=2, max_length=12)


def initial_config(config_file: Path | None) -> RuntimeConfig:
    if config_file and config_file.is_file():
        return RuntimeConfig.model_validate_json(config_file.read_text())
    return RuntimeConfig(threads=int(os.getenv("NLLB_THREADS", "2")))


class Runtime:
    def __init__(self, model_dir: Path, tokenizer_dir: Path, config: RuntimeConfig, *,
                 config_file: Path | None = None, translator_factory=None, tokenizer_factory=None,
                 clock=time.monotonic):
        self.model_dir, self.tokenizer_dir = model_dir, tokenizer_dir
        self.config_file = config_file
        self._translator_factory = translator_factory or ctranslate2.Translator
        self._tokenizer_factory = tokenizer_factory or AutoTokenizer.from_pretrained
        self._clock = clock
        self._state = threading.Lock()
        self._tokenizer_lock = threading.Lock()
        self._reload_lock = threading.Lock()
        self._translator = self._tokenizer = None
        self._config = config
        self._ready = self._reloading = False
        self._active = 0
        self._semaphore = threading.BoundedSemaphore(config.max_concurrent_requests)
        self._next_request = 0.0

    def status(self):
        with self._state:
            return self._config.model_dump() | {"ready": self._ready, "reloading": self._reloading}

    def _load(self, config: RuntimeConfig):
        if not self.model_dir.is_dir() or not self.tokenizer_dir.is_dir():
            raise ValueError("local model/tokenizer directory missing")
        tokenizer = self._tokenizer_factory(
            str(self.tokenizer_dir), local_files_only=True, trust_remote_code=False, use_fast=True,
        )
        if tokenizer.eos_token != "</s>" or tokenizer.eos_token_id is None:
            raise ValueError("expected an NLLB tokenizer with </s> EOS")
        translator = self._translator_factory(
            str(self.model_dir), device="cpu", compute_type=config.compute_type,
            inter_threads=config.max_concurrent_requests, intra_threads=config.threads,
            max_queued_batches=1,
        )
        return tokenizer, translator

    def start(self):
        self.reload(self._config, persist=False)

    def _persist(self, config: RuntimeConfig):
        if self.config_file is None:
            return
        # Parent must be provisioned as a writable persistent volume by the operator.
        temporary = self.config_file.with_name(self.config_file.name + ".tmp")
        try:
            with temporary.open("w") as stream:
                stream.write(config.model_dump_json())
                stream.flush()
                os.fsync(stream.fileno())
            temporary.replace(self.config_file)
        finally:
            temporary.unlink(missing_ok=True)

    def reload(self, config: RuntimeConfig, *, persist=True, expected=None):
        if not self._reload_lock.acquire(blocking=False):
            raise HTTPException(409, "model reload already in progress")
        try:
            with self._state:
                if self._active:
                    raise HTTPException(409, "translation active; retry configuration update later")
                old_config = self._config
                if expected is not None and self._config != expected:
                    raise HTTPException(409, "configuration changed; retry update")
                self._ready, self._reloading = False, True
                # Admission is closed and no request owns a model reference.
                self._translator = self._tokenizer = None
            gc.collect()
            try:
                tokenizer, translator = self._load(config)
                if persist:
                    self._persist(config)
            except Exception as exc:
                # Release a successfully loaded candidate before rollback (including persist failures).
                tokenizer = translator = None
                gc.collect()
                logger.exception("NLLB reload failed; attempting rollback")
                try:
                    tokenizer, translator = self._load(old_config)
                except Exception:
                    tokenizer = translator = None
                    logger.exception("NLLB rollback failed; service unavailable")
                with self._state:
                    self._tokenizer, self._translator = tokenizer, translator
                    self._ready = translator is not None
                    self._reloading = False
                raise HTTPException(503, "model reload failed; previous configuration retained") from exc
            with self._state:
                self._tokenizer, self._translator, self._config = tokenizer, translator, config
                self._semaphore = threading.BoundedSemaphore(config.max_concurrent_requests)
                self._ready, self._reloading = True, False
        finally:
            self._reload_lock.release()

    def translate(self, request: TranslationRequest):
        with self._state:
            if not self._ready:
                raise HTTPException(503, "model unavailable")
            semaphore = self._semaphore
            if not semaphore.acquire(blocking=False):
                raise HTTPException(429, "translation concurrency limit reached")
            now = self._clock()
            if now < self._next_request:
                semaphore.release()
                raise HTTPException(429, "global translation rate limit reached")
            self._next_request = now + 60.0 / self._config.global_rate_per_minute
            self._active += 1
            tokenizer, translator, config = self._tokenizer, self._translator, self._config
        try:
            # src_lang changes tokenizer state; only preparation/decoding need this lock.
            with self._tokenizer_lock:
                source = LANGUAGES.get(request.source.lower(), request.source)
                target = LANGUAGES.get(request.target.lower(), request.target)
                language_tokens = set(tokenizer.additional_special_tokens)
                if source not in language_tokens or target not in language_tokens:
                    raise HTTPException(422, "unsupported NLLB language code")
                tokenizer.src_lang = source
                source_ids = tokenizer.encode(request.text, add_special_tokens=True, truncation=False)
                if len(source_ids) > config.max_input_tokens:
                    raise HTTPException(413, "input exceeds configured token limit")
                source_tokens = tokenizer.convert_ids_to_tokens(source_ids)
                eos_token = tokenizer.eos_token
            result = translator.translate_batch(
                [source_tokens], target_prefix=[[target]], beam_size=config.beam_size,
                max_decoding_length=config.max_output_tokens, max_input_length=0,
                end_token=eos_token, return_end_token=True,
            )[0]
            output = result.hypotheses[0]
            if not output or output[-1] != eos_token:
                raise HTTPException(413, "output exceeds configured token limit")
            if output and output[0] == target:
                output = output[1:]
            with self._tokenizer_lock:
                return tokenizer.decode(tokenizer.convert_tokens_to_ids(output), skip_special_tokens=True)
        finally:
            with self._state:
                self._active -= 1
                semaphore.release()


runtime = Runtime(MODEL_DIR, TOKENIZER_DIR, initial_config(CONFIG_FILE), config_file=CONFIG_FILE)


@asynccontextmanager
async def lifespan(app):
    try:
        runtime.start()
    except HTTPException:
        logger.exception("NLLB startup failed")
    yield


app = FastAPI(title="NLLB translation service", lifespan=lifespan)


def authorized(authorization: str | None, *, require_key=False):
    if require_key and not API_KEY:
        raise HTTPException(503, "NLLB_API_KEY must be configured for runtime administration")
    if API_KEY and not hmac.compare_digest(authorization or "", f"Bearer {API_KEY}"):
        raise HTTPException(401, "Unauthorized")


@app.get("/health")
def health():
    state = runtime.status()
    if not state["ready"]:
        from fastapi.responses import JSONResponse
        return JSONResponse(status_code=503, content={"ok": False, "ready": False, "model": MODEL_DIR.name})
    return {"ok": True, "ready": True, "model": MODEL_DIR.name}


@app.get("/runtime/health")
def runtime_health(authorization: str | None = Header(default=None)):
    authorized(authorization, require_key=True)
    return health()


@app.get("/runtime")
def get_runtime(authorization: str | None = Header(default=None)):
    authorized(authorization, require_key=True)
    return runtime.status()


@app.put("/runtime")
def put_runtime(update: RuntimeUpdate, authorization: str | None = Header(default=None)):
    authorized(authorization, require_key=True)
    # Serialize merge and update: simultaneous patches cannot overwrite one another.
    if any(value is None for value in update.model_dump(exclude_unset=True).values()):
        raise HTTPException(422, "runtime fields cannot be null")
    previous = RuntimeConfig.model_validate(
        {key: value for key, value in runtime.status().items() if key in RuntimeConfig.model_fields}
    )
    candidate = RuntimeConfig.model_validate(previous.model_dump() | update.model_dump(exclude_unset=True))
    runtime.reload(candidate, expected=previous)
    return runtime.status()


@app.post("/translate")
def translate(request: TranslationRequest, authorization: str | None = Header(default=None)):
    authorized(authorization)
    return {"translated_text": runtime.translate(request)}
