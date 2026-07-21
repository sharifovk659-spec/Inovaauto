#!/usr/bin/env python3
"""Generate WebP variants (768px mobile, 1920px desktop) for homepage/banner assets."""
from __future__ import annotations

import io
import sys
import urllib.request
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
MOBILE_W = 768
DESKTOP_W = 1920
WEBP_QUALITY = 82

HERO_DESKTOP_URL = (
    'https://images.unsplash.com/photo-1503376780353-7e6692767b70'
    '?w=1920&q=80&auto=format&fit=crop'
)

IMAGE_EXTS = {'.png', '.jpg', '.jpeg', '.webp'}


def resize_to_webp(src: Path, dest: Path, max_width: int) -> tuple[int, int]:
    dest.parent.mkdir(parents=True, exist_ok=True)
    with Image.open(src) as im:
        im = im.convert('RGBA') if im.mode in ('P', 'LA') else im.convert('RGB')
        w, h = im.size
        if w > max_width:
            nh = max(1, round(h * max_width / w))
            im = im.resize((max_width, nh), Image.Resampling.LANCZOS)
        im.save(dest, 'WEBP', quality=WEBP_QUALITY, method=6)
        with Image.open(dest) as out:
            return out.size


def emit_variants(src: Path, out_dir: Path, stem: str) -> None:
    if not src.is_file():
        return
    mobile = out_dir / f'{stem}-{MOBILE_W}.webp'
    desktop = out_dir / f'{stem}-{DESKTOP_W}.webp'
    mw, mh = resize_to_webp(src, mobile, MOBILE_W)
    dw, dh = resize_to_webp(src, desktop, DESKTOP_W)
    print(f'  {src.name} -> {mobile.name} ({mw}x{mh}), {desktop.name} ({dw}x{dh})')


def process_dir(source_dir: Path, webp_subdir: str = 'webp') -> int:
    count = 0
    if not source_dir.is_dir():
        return 0
    out_dir = source_dir / webp_subdir
    for src in sorted(source_dir.iterdir()):
        if src.suffix.lower() not in IMAGE_EXTS or src.is_dir():
            continue
        if src.parent.name == 'webp':
            continue
        emit_variants(src, out_dir, src.stem)
        count += 1
    return count


def process_file(source: Path, out_dir: Path, stem: str | None = None) -> int:
    if not source.is_file():
        return 0
    stem = stem or source.stem
    emit_variants(source, out_dir, stem)
    return 1


def fetch_hero_desktop(target: Path) -> Path | None:
    target.parent.mkdir(parents=True, exist_ok=True)
    tmp = target.with_suffix('.jpg')
    try:
        req = urllib.request.Request(HERO_DESKTOP_URL, headers={'User-Agent': 'InnovaAuto-optimizer/1.0'})
        with urllib.request.urlopen(req, timeout=60) as resp:
            tmp.write_bytes(resp.read())
        return tmp
    except Exception as exc:
        print(f'  hero desktop download skipped: {exc}', file=sys.stderr)
        return None


def main() -> int:
    total = 0
    print('Categories (Imeg/categories)...')
    total += process_dir(ROOT / 'Imeg' / 'categories')

    print('IMG folder...')
    img_dir = ROOT / 'IMG'
    if img_dir.is_dir():
        out = img_dir / 'webp'
        for src in sorted(img_dir.iterdir()):
            if src.suffix.lower() not in IMAGE_EXTS or src.is_dir():
                continue
            total += process_file(src, out, src.stem)

    print('uploads/banners...')
    banners = ROOT / 'uploads' / 'banners'
    if banners.is_dir():
        out = banners / 'webp'
        for src in banners.rglob('*'):
            if src.suffix.lower() not in IMAGE_EXTS:
                continue
            if 'webp' in src.parts:
                continue
            rel_stem = src.relative_to(banners).with_suffix('').as_posix().replace('/', '__')
            emit_variants(src, out, rel_stem)
            total += 1

    print('Desktop hero (IMG/hero)...')
    hero_dir = ROOT / 'IMG' / 'hero'
    hero_src = hero_dir / 'desktop-source.jpg'
    if not hero_src.is_file():
        downloaded = fetch_hero_desktop(hero_src)
        if downloaded:
            hero_src = downloaded
    if hero_src.is_file():
        total += process_file(hero_src, hero_dir, 'desktop')
    else:
        print('  no desktop hero source')

    print(f'Done. Processed {total} source image(s).')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
