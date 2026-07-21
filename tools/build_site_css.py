#!/usr/bin/env python3
"""Backward-compatible wrapper — runs tools/build_assets.py."""
from __future__ import annotations

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_assets import main  # noqa: E402

if __name__ == '__main__':
    raise SystemExit(main())
