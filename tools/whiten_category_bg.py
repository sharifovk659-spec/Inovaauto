"""Rebuild category WebP variants from transparent PNG sources (keep alpha)."""
from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
CAT = ROOT / "Imeg" / "categories"
WEBP = CAT / "webp"


def to_webp(src: Path, dest: Path, width: int) -> None:
    im = Image.open(src).convert("RGBA")
    w, h = im.size
    if w != width:
        nh = max(1, round(h * width / w))
        im = im.resize((width, nh), Image.Resampling.LANCZOS)
    dest.parent.mkdir(parents=True, exist_ok=True)
    im.save(dest, format="WEBP", quality=90, method=6)


def main() -> int:
    pngs = sorted(CAT.glob("*.png"))
    if not pngs:
        print("No PNG sources found")
        return 1

    for src in pngs:
        stem = src.stem
        for width, suffix in ((768, "-768"), (1920, "-1920")):
            dest = WEBP / f"{stem}{suffix}.webp"
            to_webp(src, dest, width)
            print(f"{src.name} -> {dest.name}")

    # motorcycle may be jpg only — leave as-is unless png exists
    moto = CAT / "motorcycle.png"
    if moto.is_file():
        for width, suffix in ((768, "-768"), (1920, "-1920")):
            dest = WEBP / f"motorcycle{suffix}.webp"
            to_webp(moto, dest, width)
            print(f"motorcycle.png -> {dest.name}")

    print("done")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
