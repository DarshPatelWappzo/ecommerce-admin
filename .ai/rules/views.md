---
paths:
  - 'resources/views/**/index.blade.php'
---

# Views

## Use the shared GET filter drawer for listings
Place filter-button beside page-level actions and provide module-specific filter-field controls inside filter-offcanvas. Keep the drawer in the layout overlays stack, outside AJAX table replacement containers. Filters use GET and clean named-route Reset links; preserve query strings in pagination. Count only nonempty filter values (including 0), excluding sorting/pagination controls. Reuse repository filtering and validated request whitelists rather than inline controller queries.

## Shared listing header and table styles
Use listing-page-header for page titles/actions and dashboard-card listing-table-card around a table-responsive wrapper. Listing tables use table listing-table align-middle mb-0, with listing-empty for empty rows and listing-table-footer for pagination outside the horizontal scroll area. These shared app.css classes reuse the established user-table appearance; keep filters, AJAX container attributes, permissions, and row actions intact.

## Reuse the shared listing search and status presentation
Use x-listing-search inside listing-table-card for listings that already support search. Preserve current scalar GET filters and sorting, omit page when searching, and keep filter-offcanvas in the overlays stack. Pass existing paginator totals only; do not add summary queries for presentation. Use x-status-badge for lifecycle labels without changing stored values or permission checks. Shared SaaS styling lives in public/css/app.css and select2-overrides.css; preserve existing JS selectors, AJAX containers and form contracts.
