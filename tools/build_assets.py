#!/usr/bin/env python3
"""
Build minified CSS + JS for production (UI/behavior unchanged).

Usage:
  python tools/build_assets.py

Rebuild after editing site.css, add-listing-premium.css, or any assets/*.js
"""
from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

CSS_BUILDS = [
    ('assets/site.css', 'assets/site.min.css'),
    ('assets/auth-premium.css', 'assets/auth-premium.min.css'),
    ('assets/add-listing-premium.css', 'assets/add-listing-premium.min.css'),
    ('admin/assets/admin.css', 'admin/assets/admin.min.css'),
]

JS_BUILDS = [
    ('assets/js/ia-preloader.js', 'assets/js/ia-preloader.min.js'),
    ('assets/js/car-share.js', 'assets/js/car-share.min.js'),
    ('assets/js/car-map.js', 'assets/js/car-map.min.js'),
    ('assets/js/home-brands-slider.js', 'assets/js/home-brands-slider.min.js'),
    ('assets/chat.js', 'assets/chat.min.js'),
    ('assets/add-listing-photo-qa.js', 'assets/add-listing-photo-qa.min.js'),
    ('assets/add-listing-photo-camera.js', 'assets/add-listing-photo-camera.min.js'),
]


def strip_css_comments(css: str) -> str:
    """Remove comments only; keep strings and all meaningful whitespace."""
    out: list[str] = []
    i = 0
    n = len(css)
    in_str = False
    quote = ''
    in_comment = False

    while i < n:
        if in_comment:
            end = css.find('*/', i)
            if end == -1:
                break
            i = end + 2
            in_comment = False
            continue

        if in_str:
            out.append(css[i])
            if css[i] == '\\' and i + 1 < n:
                i += 1
                out.append(css[i])
            elif css[i] == quote:
                in_str = False
            i += 1
            continue

        if css.startswith('/*', i):
            in_comment = True
            i += 2
            continue

        ch = css[i]
        if ch in '"\'':
            in_str = True
            quote = ch
            out.append(ch)
            i += 1
            continue

        out.append(ch)
        i += 1

    return ''.join(out)


def minify_css(css: str) -> str:
    """
    Safe CSS minify: keeps required spaces in values (0 2px 8px) and selectors (] body).
    """
    css = strip_css_comments(css.replace('\r\n', '\n'))
    css = re.sub(r'\s+', ' ', css)
    css = re.sub(r'\s*{\s*', '{', css)
    css = re.sub(r'\s*}\s*', '}', css)
    css = re.sub(r'\s*;\s*', ';', css)
    css = re.sub(r'\s*:\s*', ':', css)
    css = re.sub(r'\s*,\s*', ',', css)
    css = re.sub(r'\s*>\s*', '>', css)
    css = re.sub(r'\s*\+\s*', '+', css)
    css = re.sub(r'\s*~\s*', '~', css)
    css = re.sub(r';}', '}', css)

    return css.strip() + '\n'


def strip_debug_console(js: str) -> str:
    """Remove console.log/debug/info for production .min.js only."""
    js = re.sub(
        r'^\s*console\.(?:log|debug|info)\s*\([^;]*\)\s*;?\s*\n?',
        '',
        js,
        flags=re.MULTILINE,
    )
    return js


def minify_js(js: str, *, production: bool = True) -> str:
    if production:
        js = strip_debug_console(js)

    out: list[str] = []
    i = 0
    n = len(js)
    in_str = False
    quote = ''
    in_line_comment = False
    in_block_comment = False
    prev = ''

    while i < n:
        if in_line_comment:
            if js[i] == '\n':
                in_line_comment = False
                out.append('\n')
            i += 1
            continue

        if in_block_comment:
            end = js.find('*/', i)
            if end == -1:
                break
            i = end + 2
            in_block_comment = False
            continue

        if in_str:
            out.append(js[i])
            if js[i] == '\\' and i + 1 < n:
                i += 1
                out.append(js[i])
            elif js[i] == quote:
                in_str = False
            i += 1
            prev = js[i]
            continue

        if js.startswith('//', i):
            in_line_comment = True
            i += 2
            continue

        if js.startswith('/*', i):
            in_block_comment = True
            i += 2
            continue

        ch = js[i]
        if ch in '"\'`':
            in_str = True
            quote = ch
            out.append(ch)
            i += 1
            prev = ch
            continue

        if ch.isspace():
            if prev and prev not in '({[=,:;+-*/%&|^<>!~?':
                if i + 1 < n and js[i + 1] not in '})];,+-*/%&|^<>!?:':
                    out.append(' ')
            i += 1
            prev = ' '
            continue

        out.append(ch)
        i += 1
        prev = ch

    result = ''.join(out)
    result = re.sub(r'\n{2,}', '\n', result)
    return result.strip() + '\n'


def build_pair(src_rel: str, dst_rel: str, *, kind: str) -> tuple[int, int]:
    src = ROOT / src_rel
    dst = ROOT / dst_rel
    if not src.is_file():
        print(f'  skip (missing): {src_rel}')
        return 0, 0
    raw = src.read_text(encoding='utf-8')
    mini = minify_css(raw) if kind == 'css' else minify_js(raw, production=True)
    dst.write_text(mini, encoding='utf-8', newline='\n')
    return len(raw.encode('utf-8')), len(mini.encode('utf-8'))


def main() -> int:
    print('InnovaAuto assets build')
    total_raw = 0
    total_mini = 0

    print('CSS:')
    for src_rel, dst_rel in CSS_BUILDS:
        raw_b, mini_b = build_pair(src_rel, dst_rel, kind='css')
        if raw_b:
            pct = 100 - round(mini_b * 100 / raw_b) if raw_b else 0
            print(f'  {src_rel} -> {dst_rel}  ({raw_b // 1024} KB -> {mini_b // 1024} KB, -{pct}%)')
            total_raw += raw_b
            total_mini += mini_b

    print('JS:')
    for src_rel, dst_rel in JS_BUILDS:
        raw_b, mini_b = build_pair(src_rel, dst_rel, kind='js')
        if raw_b:
            pct = 100 - round(mini_b * 100 / raw_b) if raw_b else 0
            print(f'  {src_rel} -> {dst_rel}  ({raw_b // 1024} KB -> {mini_b // 1024} KB, -{pct}%)')
            total_raw += raw_b
            total_mini += mini_b

    print(f'Done. Total {total_raw // 1024} KB -> {total_mini // 1024} KB')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
