from pathlib import Path

p = Path(__file__).resolve().parents[1] / "assets" / "site.css"
text = p.read_text(encoding="utf-8")
start = text.index("/* ===== Каталог — мобильная вёрстка ===== */")
end = text.index("[data-bs-theme='dark'] .ia-mobile-tabbar", start)

new = r"""/* ===== Каталог — мобильная вёрстка ===== */
@media (max-width: 991.98px) {
  body.ia-page-catalog .ia-nav .navbar-brand {
    display: none !important;
  }

  body.ia-page-catalog .ia-mobile-header {
    flex: 1 1 auto;
    min-width: 0;
    max-width: 100%;
  }

  .ia-page-catalog .ia-catalog-page-section,
  .ia-page-catalog .ia-page-section {
    padding-top: 0.65rem !important;
    padding-bottom: 1rem !important;
  }

  .ia-page-catalog .ia-page-section > .container {
    padding-left: 0.65rem;
    padding-right: 0.65rem;
  }

  .ia-page-catalog .ia-catalog-mobile-head {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    margin-bottom: 0.65rem;
  }

  .ia-page-catalog .ia-catalog-mobile-head .ia-catalog-page-title {
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.2;
  }

  .ia-page-catalog .ia-catalog-mobile-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.45rem 0.55rem;
    border-radius: 12px;
    border: 1px solid var(--ia-border);
    background: var(--ia-card);
  }

  .ia-page-catalog .ia-catalog-mobile-count {
    font-size: 0.78rem;
    color: var(--ia-muted);
    min-width: 0;
  }

  .ia-page-catalog .ia-catalog-mobile-count strong {
    color: var(--ia-text);
    font-weight: 700;
  }

  .ia-page-catalog .ia-catalog-filter-toggle {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    border-radius: 999px;
    border: 1px solid rgba(59, 130, 246, 0.35);
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
    font-size: 0.74rem;
    font-weight: 600;
    padding: 0.32rem 0.65rem;
    white-space: nowrap;
  }

  .ia-page-catalog .ia-catalog-filter-toggle[aria-expanded='true'] {
    background: rgba(59, 130, 246, 0.18);
    border-color: rgba(59, 130, 246, 0.45);
  }

  .ia-page-catalog .ia-catalog-aside {
    margin-bottom: 0.45rem;
  }

  .ia-page-catalog .ia-catalog-filters-collapse:not(.show) {
    display: none;
  }

  .ia-page-catalog .ia-catalog-filters-collapse.show {
    margin-bottom: 0.5rem;
  }

  .ia-page-catalog .ia-catalog-field--q {
    display: none;
  }

  .ia-page-catalog .ia-catalog-filters {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.42rem 0.38rem;
    padding: 0.7rem !important;
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
  }

  .ia-page-catalog .ia-catalog-filters-title {
    grid-column: 1 / -1;
    font-size: 0.68rem;
    letter-spacing: 0.08em;
    margin-bottom: 0.1rem !important;
  }

  .ia-page-catalog .ia-catalog-field {
    margin-bottom: 0 !important;
  }

  .ia-page-catalog .ia-catalog-field--full,
  .ia-page-catalog .ia-catalog-filters-actions {
    grid-column: 1 / -1;
  }

  .ia-page-catalog .ia-catalog-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.1rem;
    font-weight: 600;
    color: var(--ia-muted);
  }

  .ia-page-catalog .ia-catalog-filters .form-control,
  .ia-page-catalog .ia-catalog-filters .form-select {
    min-height: 36px;
    height: 36px;
    font-size: 16px;
    border-radius: 9px;
    padding: 0.26rem 0.42rem;
  }

  .ia-page-catalog .ia-catalog-filters .d-flex.gap-2 {
    gap: 0.32rem !important;
  }

  .ia-page-catalog .ia-catalog-filters-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.35rem;
  }

  .ia-page-catalog .ia-catalog-filters-actions .btn {
    min-height: 38px;
    font-size: 0.82rem;
    margin: 0;
  }

  .ia-page-catalog .ia-catalog-main {
    min-width: 0;
  }

  .ia-page-catalog .ia-catalog-fuzzy-hint {
    font-size: 0.72rem;
    padding: 0.45rem 0.55rem !important;
    margin-bottom: 0.55rem !important;
  }

  .ia-page-catalog .ia-catalog-empty {
    padding: 1.5rem 0.85rem;
    border-radius: 14px;
  }

  .ia-page-catalog .ia-catalog-empty h2 {
    font-size: 1rem;
  }

  .ia-page-catalog .row.g-4 > .col-12.col-sm-6.col-lg-3 {
    flex: 0 0 50%;
    max-width: 50%;
    padding-left: 0.25rem;
    padding-right: 0.25rem;
  }

  .ia-page-catalog .row.g-4 {
    --bs-gutter-x: 0.5rem;
    --bs-gutter-y: 0.55rem;
    margin-left: -0.25rem;
    margin-right: -0.25rem;
  }

  .ia-page-catalog .ia-listing-card--catalog {
    border-radius: 12px;
    height: 100%;
    overflow: hidden;
  }

  .ia-page-catalog .ia-listing-card--catalog .ia-listing-card-body {
    padding: 0.5rem !important;
  }

  .ia-page-catalog .ia-listing-card--catalog .ia-listing-card-img-wrap--square {
    aspect-ratio: 1 / 1;
  }

  .ia-page-catalog .ia-card-title-clamp {
    font-size: 0.78rem;
    line-height: 1.25;
    -webkit-line-clamp: 2;
  }

  .ia-page-catalog .ia-listing-card-meta {
    gap: 0.15rem;
    flex-wrap: wrap;
  }

  .ia-page-catalog .ia-price-card {
    font-size: 0.8rem;
    max-width: 62%;
    text-align: right;
  }

  .ia-page-catalog .ia-listing-card-id {
    font-size: 0.56rem;
    margin-bottom: 0.28rem !important;
  }

  .ia-page-catalog .ia-listing-specs-dl {
    font-size: 0.64rem;
  }

  .ia-page-catalog .ia-listing-specs-dl dt {
    font-size: 0.55rem !important;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  .ia-page-catalog .ia-listing-specs-dl dd {
    font-size: 0.64rem;
    font-weight: 600;
  }

  .ia-page-catalog .ia-badge-availability {
    font-size: 0.54rem;
    padding: 0.1rem 0.32rem;
  }

  .ia-page-catalog .ia-listing-views-inline {
    font-size: 0.58rem;
    gap: 0.08rem;
  }

  .ia-page-catalog .ia-listing-views-inline .bi {
    font-size: 0.62rem;
    color: var(--ia-text);
  }

  .ia-page-catalog .ia-card-icon-btn {
    width: 1.85rem;
    height: 1.85rem;
  }

  .ia-page-catalog .ia-catalog-main nav .btn {
    min-width: 2rem;
    padding: 0.28rem 0.45rem;
    font-size: 0.78rem;
  }

  .ia-page-home .ia-listing-views-inline {
    font-size: 0.62rem;
    gap: 0.1rem;
  }

  .ia-page-home .ia-listing-views-inline .bi {
    font-size: 0.66rem;
    color: var(--ia-text);
  }

  .ia-footer-contact-list li {
    gap: 0.28rem;
  }

  .ia-footer-contact-ico {
    width: 18px;
    height: 18px;
  }

  .ia-footer-social {
    gap: 0.22rem;
  }

  .ia-footer-pro .ia-footer-social-btn {
    width: 1.85rem;
    height: 1.85rem;
  }

  .ia-footer-pro .ia-footer-social-btn svg {
    width: 14px;
    height: 14px;
  }

  .ia-mobile-header-sell {
    font-size: 0.58rem;
    padding: 0.36rem 0.48rem;
    letter-spacing: -0.01em;
  }
}

@media (min-width: 992px) {
  .ia-page-catalog .ia-catalog-filters-collapse {
    display: block !important;
    height: auto !important;
    visibility: visible !important;
  }
}

"""

p.write_text(text[:start] + new + text[end:], encoding="utf-8")
print("patched catalog mobile css")
