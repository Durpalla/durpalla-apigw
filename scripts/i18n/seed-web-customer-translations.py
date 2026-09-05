#!/usr/bin/env python3
"""Seed web-customer common.json for all supported locales."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
APP = "web-customer"
LOCALES = ["en", "bn", "hi", "ar", "zh", "ur", "fa", "tr", "es", "it"]

COMMON: dict[str, dict] = {
    "en": {
        "nav": {"bus": "Bus", "launch": "Launch", "boat": "Boat", "hotels": "Hotels"},
        "language": {"label": "Language", "switch": "Change language"},
    },
    "bn": {
        "nav": {"bus": "বাস", "launch": "লঞ্চ", "boat": "নৌকা", "hotels": "হোটেল"},
        "language": {"label": "ভাষা", "switch": "ভাষা পরিবর্তন"},
    },
    "hi": {
        "nav": {"bus": "बस", "launch": "लॉन्च", "boat": "नाव", "hotels": "होटल"},
        "language": {"label": "भाषा", "switch": "भाषा बदलें"},
    },
    "ar": {
        "nav": {"bus": "حافلة", "launch": "عبّارة", "boat": "قارب", "hotels": "فنادق"},
        "language": {"label": "اللغة", "switch": "تغيير اللغة"},
    },
    "zh": {
        "nav": {"bus": "巴士", "launch": "客轮", "boat": "船", "hotels": "酒店"},
        "language": {"label": "语言", "switch": "更改语言"},
    },
    "ur": {
        "nav": {"bus": "بس", "launch": "لانچ", "boat": "کشتی", "hotels": "ہوٹل"},
        "language": {"label": "زبان", "switch": "زبان تبدیل کریں"},
    },
    "fa": {
        "nav": {"bus": "اتوبوس", "launch": "لانچ", "boat": "قایق", "hotels": "هتل"},
        "language": {"label": "زبان", "switch": "تغییر زبان"},
    },
    "tr": {
        "nav": {"bus": "Otobüs", "launch": "Vapur", "boat": "Tekne", "hotels": "Oteller"},
        "language": {"label": "Dil", "switch": "Dili değiştir"},
    },
    "es": {
        "nav": {"bus": "Autobús", "launch": "Lancha", "boat": "Barco", "hotels": "Hoteles"},
        "language": {"label": "Idioma", "switch": "Cambiar idioma"},
    },
    "it": {
        "nav": {"bus": "Autobus", "launch": "Traghetto", "boat": "Barca", "hotels": "Hotel"},
        "language": {"label": "Lingua", "switch": "Cambia lingua"},
    },
}


def main() -> None:
    for locale in LOCALES:
        locale_dir = ROOT / "resources" / "localizations" / APP / locale
        locale_dir.mkdir(parents=True, exist_ok=True)
        common_path = locale_dir / "common.json"
        common_path.write_text(
            json.dumps(COMMON[locale], ensure_ascii=False, indent=4) + "\n",
            encoding="utf-8",
        )
        manifest_path = locale_dir / "manifest.json"
        if not manifest_path.exists():
            manifest_path.write_text(
                json.dumps(
                    {
                        "app": APP,
                        "locale": locale,
                        "version": 1,
                        "fallback_locale": "en",
                        "format": "i18next-namespaces",
                        "key_count": 6,
                        "exported_at": "2026-09-05T00:00:00+00:00",
                    },
                    indent=4,
                )
                + "\n",
                encoding="utf-8",
            )
    print(f"Seeded web-customer common.json for {len(LOCALES)} locales.")


if __name__ == "__main__":
    main()
