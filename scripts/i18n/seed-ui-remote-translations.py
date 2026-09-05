#!/usr/bin/env python3
"""
Translate remote UI locale files (hi, ar, zh, ur, fa, tr, es, it) from English exports.
Apps: customer-app, merchant-desk (messages.json), web-merchant (namespace json files).
"""

from __future__ import annotations

import json
import sys
import time
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "resources" / "localizations"
REMOTE = ["hi", "ar", "zh", "ur", "fa", "tr", "es", "it"]
APPS_FLAT = ["customer-app", "merchant-desk"]
APPS_NS = ["web-merchant"]

LOCALES = {
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
    import subprocess

    subprocess.check_call([sys.executable, "-m", "pip", "install", "deep-translator", "-q"])
    from deep_translator import GoogleTranslator


def translate_value(text: str, target: str, cache: dict[tuple[str, str], str]) -> str:
    if not isinstance(text, str) or not text.strip():
        return text
    key = (target, text)
    if key in cache:
        return cache[key]
    try:
        out = GoogleTranslator(source="en", target=target).translate(text)
    except Exception as exc:  # noqa: BLE001
        print(f"  warn: {exc!r}", file=sys.stderr)
        out = text
    cache[key] = out
    time.sleep(0.06)
    return out


def walk_translate(obj: Any, target: str, cache: dict[tuple[str, str], str]) -> Any:
    if isinstance(obj, dict):
        return {k: walk_translate(v, target, cache) for k, v in obj.items()}
    if isinstance(obj, str):
        return translate_value(obj, target, cache)
    return obj


def translate_flat_messages(en_path: Path, out_path: Path, target: str, cache: dict) -> None:
    data: dict[str, str] = json.loads(en_path.read_text(encoding="utf-8"))
    total = len(data)
    translated: dict[str, str] = {}
    for i, (k, v) in enumerate(data.items(), 1):
        translated[k] = translate_value(v, target, cache)
        if i % 100 == 0:
            print(f"    {i}/{total}")
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(translated, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")


def main() -> None:
    cache: dict[tuple[str, str], str] = {}

    for app in APPS_FLAT:
        en_file = BASE / app / "en" / "messages.json"
        if not en_file.exists():
            print(f"skip missing {en_file}")
            continue
        for locale in REMOTE:
            google = LOCALES[locale]
            out = BASE / app / locale / "messages.json"
            print(f"{app}/{locale} ({google})...")
            translate_flat_messages(en_file, out, google, cache)

    for app in APPS_NS:
        en_dir = BASE / app / "en"
        for locale in REMOTE:
            google = LOCALES[locale]
            out_dir = BASE / app / locale
            out_dir.mkdir(parents=True, exist_ok=True)
            for en_ns in sorted(en_dir.glob("*.json")):
                if en_ns.name == "manifest.json":
                    continue
                raw = json.loads(en_ns.read_text(encoding="utf-8"))
                translated = walk_translate(raw, google, cache)
                (out_dir / en_ns.name).write_text(
                    json.dumps(translated, ensure_ascii=False, indent=4) + "\n",
                    encoding="utf-8",
                )
            print(f"{app}/{locale} namespaces done")

    print("Remote UI translations complete.")


if __name__ == "__main__":
    main()
