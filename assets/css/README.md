# CSS structure (InnovaAuto)

## Production (hosting)

| File | Role |
|------|------|
| `assets/site.min.css` | Main public styles (minified) |
| `assets/site.css` | Source — edit this, then run build |
| `assets/critical/above-fold.css` | Critical mobile canvas — inlined in `<head>` |
| `assets/add-listing-premium.min.css` | Add-listing page |
| `admin/assets/admin.min.css` | Admin panel |

## Build (CSS + JS)

```bash
python tools/build_assets.py
```

After changing `site.css` or any `assets/**/*.js`, run the script and upload both source and `.min.*` files to hosting.

## Local development

In `.env` set `IA_CSS_DEV=true` to load unminified `site.css` (easier debugging in DevTools).

## Sections in `site.css`

The file is one bundle (safe for shared hosting). Major blocks are marked with `/* === ... === */` comments (header, hero, catalog, car, compare, footer, mobile overrides).
