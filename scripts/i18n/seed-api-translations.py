#!/usr/bin/env python3
"""
Translate API message keys (lang/en.json) into all supported locales.
Uses deep-translator (Google Translate, no API key).
"""

from __future__ import annotations

import json
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
EN_PATH = ROOT / "lang" / "en.json"

LOCALES = {
    "bn": "bn",
    "hi": "hi",
    "ar": "ar",
    "zh": "zh-CN",
    "ur": "ur",
    "fa": "fa",
    "tr": "tr",
    "es": "es",
    "it": "it",
}

try:
    from deep_translator import GoogleTranslator
except ImportError:
    print("Installing deep-translator...", file=sys.stderr)
    import subprocess

    subprocess.check_call([sys.executable, "-m", "pip", "install", "deep-translator", "-q"])
    from deep_translator import GoogleTranslator


def translate_text(text: str, target: str, cache: dict[tuple[str, str], str]) -> str:
    key = (target, text)
    if key in cache:
        return cache[key]
    if not text.strip():
        cache[key] = text
        return text
    try:
        translated = GoogleTranslator(source="en", target=target).translate(text)
    except Exception as exc:  # noqa: BLE001
        print(f"  warn: {exc!r} for {text[:40]!r}", file=sys.stderr)
        translated = text
    cache[key] = translated
    time.sleep(0.08)
    return translated


def main() -> None:
    en: dict[str, str] = json.loads(EN_PATH.read_text(encoding="utf-8"))
    cache: dict[tuple[str, str], str] = {}

    for file_locale, google_target in LOCALES.items():
        out_path = ROOT / "lang" / f"{file_locale}.json"
        existing: dict[str, str] = {}
        if out_path.exists():
            existing = json.loads(out_path.read_text(encoding="utf-8"))

        print(f"Translating API messages -> {file_locale} ({len(en)} keys)...")
        translated: dict[str, str] = {}
        for i, (msg_key, _en_val) in enumerate(en.items(), 1):
            if (
                file_locale == "bn"
                and msg_key in existing
                and existing[msg_key] != msg_key
                and existing[msg_key] != en.get(msg_key)
            ):
                translated[msg_key] = existing[msg_key]
                continue
            translated[msg_key] = translate_text(msg_key, google_target, cache)
            if i % 25 == 0:
                print(f"  {file_locale}: {i}/{len(en)}")

        out_path.write_text(
            json.dumps(translated, ensure_ascii=False, indent=4, sort_keys=True) + "\n",
            encoding="utf-8",
        )
        print(f"  wrote {out_path}")

    print("API lang/*.json complete.")


if __name__ == "__main__":
    main()
