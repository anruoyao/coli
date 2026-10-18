"""Mock integration and lifecycle tests; no model files or network are needed."""
import gc
import importlib.util
from pathlib import Path
import tempfile
import sys
import threading
import unittest
from types import SimpleNamespace
from unittest.mock import patch

from fastapi import HTTPException
from fastapi.testclient import TestClient

spec = importlib.util.spec_from_file_location("nllb_server", Path(__file__).with_name("server.py"))
server = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = server
spec.loader.exec_module(server)


class Tokenizer:
    eos_token, eos_token_id = "</s>", 2
    additional_special_tokens = ["eng_Latn", "zho_Hans", "rus_Cyrl"]
    src_lang = None

    def encode(self, text, **kwargs):
        assert kwargs == {"add_special_tokens": True, "truncation": False}
        return [self.src_lang, text, "</s>"]

    def convert_ids_to_tokens(self, value):
        return value

    def convert_tokens_to_ids(self, value):
        return value

    def decode(self, value, **kwargs):
        assert kwargs == {"skip_special_tokens": True}
        return " ".join(x for x in value if x not in self.additional_special_tokens + ["</s>"])


class Translator:
    def __init__(self):
        self.calls = []
        self.entered = self.resume = None
        self.output = ["zho_Hans", "translated", "</s>"]

    def translate_batch(self, source, **kwargs):
        self.calls.append((source, kwargs))
        if self.entered:
            self.entered.set()
            if not self.resume.wait(5):
                raise AssertionError("test translation never released")
        return [SimpleNamespace(hypotheses=[self.output])]


class RuntimeTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.path = Path(self.directory.name)
        self.now = 100.0
        self.translator = Translator()
        self.runtime = server.Runtime(
            self.path, self.path, server.RuntimeConfig(),
            translator_factory=lambda *a, **kw: self.translator,
            tokenizer_factory=lambda *a, **kw: Tokenizer(), clock=lambda: self.now,
        )
        self.runtime.start()
        self.request = server.TranslationRequest(text="hello", source="en", target="zh")

    def tearDown(self):
        self.directory.cleanup()

    def test_nllb_language_prefix_source_order_eos_and_decode(self):
        self.assertEqual(self.runtime.translate(self.request), "translated")
        source, options = self.translator.calls[0]
        self.assertEqual(source, [["eng_Latn", "hello", "</s>"]])
        self.assertEqual(options["target_prefix"], [["zho_Hans"]])
        self.assertEqual(options["end_token"], "</s>")
        self.assertTrue(options["return_end_token"])
        self.assertEqual(options["max_input_length"], 0)

    def test_unsupported_language_and_token_caps(self):
        self.request.source = "xyz_Latn"
        with self.assertRaises(HTTPException) as raised:
            self.runtime.translate(self.request)
        self.assertEqual(raised.exception.status_code, 422)
        self.now += 6
        self.runtime.reload(server.RuntimeConfig(max_input_tokens=2))
        self.request.source = "en"
        with self.assertRaises(HTTPException) as raised:
            self.runtime.translate(self.request)
        self.assertEqual(raised.exception.status_code, 413)
        self.assertEqual(self.translator.calls, [])

    def test_no_silent_output_truncation(self):
        self.translator.output = ["zho_Hans", "incomplete"]
        with self.assertRaises(HTTPException) as raised:
            self.runtime.translate(self.request)
        self.assertEqual(raised.exception.status_code, 413)

    def test_global_rate_and_released_permit_after_failure(self):
        self.runtime.translate(self.request)
        with self.assertRaises(HTTPException) as raised:
            self.runtime.translate(self.request)
        self.assertEqual(raised.exception.status_code, 429)
        self.now += 6
        self.assertEqual(self.runtime.translate(self.request), "translated")

    def test_busy_reload_and_inference_do_not_queue(self):
        self.translator.entered, self.translator.resume = threading.Event(), threading.Event()
        errors = []
        def invoke():
            try:
                self.runtime.translate(self.request)
            except Exception as exc:
                errors.append(exc)
        worker = threading.Thread(target=invoke)
        worker.start()
        try:
            self.assertTrue(self.translator.entered.wait(2))
            self.now += 6
            with self.assertRaises(HTTPException) as raised:
                self.runtime.translate(self.request)
            self.assertEqual(raised.exception.status_code, 429)
            with self.assertRaises(HTTPException) as raised:
                self.runtime.reload(server.RuntimeConfig(threads=1))
            self.assertEqual(raised.exception.status_code, 409)
        finally:
            self.translator.resume.set()
            worker.join(2)
        self.assertFalse(worker.is_alive())
        self.assertEqual(errors, [])
        self.runtime.reload(server.RuntimeConfig(threads=1))
        self.assertEqual(self.runtime.translate(self.request), "translated")

    def test_loading_closes_admission_and_frees_old_model_before_new(self):
        alive = []
        entered, resume = threading.Event(), threading.Event()
        class TrackedTranslator(Translator):
            def __init__(self):
                alive.append(1)
                super().__init__()
            def __del__(self):
                alive.pop()
        self.runtime._translator = TrackedTranslator()
        def factory(*args, **kwargs):
            self.assertEqual(alive, [])
            entered.set()
            if not resume.wait(5):
                raise AssertionError("reload never released")
            return TrackedTranslator()
        self.runtime._translator_factory = factory
        worker = threading.Thread(target=lambda: self.runtime.reload(server.RuntimeConfig(threads=1)))
        worker.start()
        try:
            self.assertTrue(entered.wait(2))
            with self.assertRaises(HTTPException) as raised:
                self.runtime.translate(self.request)
            self.assertEqual(raised.exception.status_code, 503)
            self.assertTrue(self.runtime.status()["reloading"])
        finally:
            resume.set()
            worker.join(2)
        self.assertTrue(self.runtime.status()["ready"])
        self.assertEqual(alive, [1])
        self.runtime._translator = None
        gc.collect()

    def test_failed_reload_rolls_back_or_marks_unready(self):
        def factory(*args, **kwargs):
            if kwargs["intra_threads"] == 1:
                raise ValueError("candidate failed")
            return self.translator
        self.runtime._translator_factory = factory
        with self.assertRaises(HTTPException):
            self.runtime.reload(server.RuntimeConfig(threads=1))
        self.assertTrue(self.runtime.status()["ready"])
        self.assertEqual(self.runtime.status()["threads"], 2)
        self.runtime._translator_factory = lambda *args, **kwargs: (_ for _ in ()).throw(ValueError("all loads fail"))
        with self.assertRaises(HTTPException):
            self.runtime.reload(server.RuntimeConfig(threads=1))
        self.assertFalse(self.runtime.status()["ready"])
        with self.assertRaises(HTTPException) as raised:
            self.runtime.translate(self.request)
        self.assertEqual(raised.exception.status_code, 503)

    def test_persisted_config_and_failure_rollback(self):
        self.runtime.config_file = self.path / "runtime.json"
        self.runtime.reload(server.RuntimeConfig(threads=1))
        self.assertEqual(server.initial_config(self.runtime.config_file).threads, 1)
        self.runtime.config_file = self.path / "missing" / "runtime.json"
        with self.assertRaises(HTTPException):
            self.runtime.reload(server.RuntimeConfig(threads=2))
        self.assertEqual(self.runtime.status()["threads"], 1)
        self.assertTrue(self.runtime.status()["ready"])

    def test_load_is_local_only_and_explicit_cpu(self):
        calls = []
        self.runtime._tokenizer_factory = lambda *a, **kw: calls.append((a, kw)) or Tokenizer()
        self.runtime._translator_factory = lambda *a, **kw: calls.append((a, kw)) or self.translator
        self.runtime.reload(server.RuntimeConfig())
        self.assertEqual(calls[0][1], {"local_files_only": True, "trust_remote_code": False, "use_fast": True})
        self.assertEqual(calls[1][1]["device"], "cpu")
        self.assertEqual(calls[1][1]["compute_type"], "int8")

    def test_parallel_inference_uses_configured_slots(self):
        self.runtime.reload(server.RuntimeConfig(max_concurrent_requests=2, global_rate_per_minute=1200))
        self.translator.entered, self.translator.resume = threading.Event(), threading.Event()
        results = []
        first = threading.Thread(target=lambda: results.append(self.runtime.translate(self.request)))
        second = threading.Thread(target=lambda: results.append(self.runtime.translate(self.request)))
        first.start()
        try:
            self.assertTrue(self.translator.entered.wait(2))
            self.now += 1
            second.start()
            # Both calls enter CT2 even while the first is blocked.
            for _ in range(200):
                if len(self.translator.calls) == 2:
                    break
                threading.Event().wait(0.005)
            self.assertEqual(len(self.translator.calls), 2)
            self.now += 1
            with self.assertRaises(HTTPException) as raised:
                self.runtime.translate(self.request)
            self.assertEqual(raised.exception.status_code, 429)
        finally:
            self.translator.resume.set()
            first.join(2)
            if second.ident is not None:
                second.join(2)
        self.assertEqual(results, ["translated", "translated"])

    def test_stale_runtime_patch_is_rejected(self):
        previous = server.RuntimeConfig()
        self.runtime.reload(server.RuntimeConfig(beam_size=1))
        with self.assertRaises(HTTPException) as raised:
            self.runtime.reload(server.RuntimeConfig(threads=1), expected=previous)
        self.assertEqual(raised.exception.status_code, 409)
        self.assertEqual(self.runtime.status()["beam_size"], 1)

    def test_http_auth_validation_and_health(self):
        with patch.object(server, "runtime", self.runtime), patch.object(server, "API_KEY", "secret"):
            client = TestClient(server.app)
            headers = {"Authorization": "Bearer secret"}
            self.assertEqual(client.get("/runtime").status_code, 401)
            self.assertEqual(client.get("/runtime/health").status_code, 401)
            self.assertEqual(client.get("/runtime", headers=headers).json()["global_rate_per_minute"], 10)
            for payload in [{"threads": 9}, {"compute_type": "float16"}, {"model_dir": "/tmp"}, {"threads": None}, {"max_output_tokens": 513}]:
                self.assertEqual(client.put("/runtime", json=payload, headers=headers).status_code, 422)
            self.assertEqual(client.put("/runtime", json={"beam_size": 1}, headers=headers).json()["beam_size"], 1)
            self.runtime._ready = False
            self.assertEqual(client.get("/health").status_code, 503)
            self.assertFalse(client.get("/health").json()["ready"])
        with patch.object(server, "API_KEY", ""):
            self.assertEqual(TestClient(server.app).get("/runtime").status_code, 503)


if __name__ == "__main__":
    unittest.main()
