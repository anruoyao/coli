"""Run with python -I /path/to/prepare_model.py from a trusted directory.

Download the official Facebook snapshot pinned to a commit, then convert locally.
Use a separate preparation environment; torch is never needed by the server.
"""
import argparse
import json
import os
from pathlib import Path
import re
import shutil

REPOSITORY = "facebook/nllb-200-distilled-600M"
TOKENIZER_FILES = ("tokenizer.json", "tokenizer_config.json", "special_tokens_map.json", "sentencepiece.bpe.model")


def fresh_directory(path: Path):
    # Refuse existing directories, including empty ones: each download gets a new location.
    path.mkdir(parents=True, exist_ok=False)


def prepare(args):
    from huggingface_hub import snapshot_download

    output = args.output_dir.resolve()
    if args.local_snapshot:
        snapshot = args.local_snapshot.resolve()
        if not snapshot.is_dir():
            raise ValueError("local snapshot directory missing")
    else:
        if not args.snapshot_dir or not args.revision or not re.fullmatch(r"[0-9a-f]{40}", args.revision):
            raise ValueError("download requires --snapshot-dir and --revision (full 40-character commit SHA)")
        snapshot = args.snapshot_dir.resolve()
        fresh_directory(snapshot)
        snapshot_download(
            REPOSITORY, revision=args.revision, local_dir=str(snapshot),
            allow_patterns=["config.json", "generation_config.json", "pytorch_model.bin", "model.safetensors", *TOKENIZER_FILES],
        )
    if output == snapshot or snapshot in output.parents or output in snapshot.parents:
        raise ValueError("output and snapshot directories must be separate")
    for filename in TOKENIZER_FILES[:3]:
        if not (snapshot / filename).is_file():
            raise ValueError(f"official tokenizer file missing: {filename}")
    config = json.loads((snapshot / "config.json").read_text())
    if config.get("model_type") != "m2m_100" or config.get("auto_map"):
        raise ValueError("expected standard NLLB m2m_100 model without custom code")
    fresh_directory(output)
    # All model loading is now offline; no dynamic code or imports from the snapshot.
    os.environ["HF_HUB_OFFLINE"] = "1"
    os.environ["TRANSFORMERS_OFFLINE"] = "1"
    from ctranslate2.converters import TransformersConverter
    from transformers import AutoTokenizer

    tokenizer = AutoTokenizer.from_pretrained(str(snapshot), local_files_only=True, trust_remote_code=False, use_fast=True)
    if tokenizer.eos_token != "</s>" or "eng_Latn" not in tokenizer.additional_special_tokens:
        raise ValueError("expected the official NLLB tokenizer")
    TransformersConverter(str(snapshot), load_as_float16=False, trust_remote_code=False).convert(
        str(output), quantization="int8", force=True,
    )
    for filename in TOKENIZER_FILES:
        if (snapshot / filename).is_file():
            shutil.copyfile(snapshot / filename, output / filename)
    (output / "provenance.json").write_text(json.dumps({
        "repository": REPOSITORY, "revision": args.revision,
        "source": str(snapshot), "quantization": "int8",
    }, indent=2) + "\n")
    print(f"Prepared local int8 model and tokenizer at {output}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--snapshot-dir", type=Path)
    parser.add_argument("--local-snapshot", type=Path)
    parser.add_argument("--revision", help="official Hugging Face repository commit SHA")
    parser.add_argument("--output-dir", type=Path, required=True)
    args = parser.parse_args()
    if args.local_snapshot and args.snapshot_dir:
        parser.error("choose --local-snapshot or --snapshot-dir")
    prepare(args)


if __name__ == "__main__":
    main()
