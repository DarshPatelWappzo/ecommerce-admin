---
paths:
  - 'app/Services/TenantOrder*.php'
---

# Services

## Order tax policy confirmed by the user
Orders use INR and tax-exclusive catalog prices with a single percentage tax. Do not infer CGST/SGST/IGST or jurisdiction rules from GSTIN. Shipping greater than zero requires staff to select an active existing tax; an explicitly configured zero rate is valid, but an unknown/unavailable tax is not zero-rated. Captured funds block cancellation until a refund workflow exists.

## Order tax policy confirmed by the user
Orders use INR and tax-exclusive catalog prices with a single percentage tax. Do not infer CGST/SGST/IGST or jurisdiction rules from GSTIN. Shipping cost and shipping GST are optional: omitted shipping cost is zero, and shipping with no selected tax has no shipping tax charge. If a shipping tax is explicitly selected, it must be active and available; an explicitly configured zero rate is valid. This supersedes the earlier requirement to select a tax for positive shipping charges. Captured funds block cancellation until a refund workflow exists.
