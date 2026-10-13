---
paths:
  - 'app/Services/PlatformGatewayConfigurationService.php, app/Services/PlatformRazorpayGateway.php'
  - 'app/Services/PackageCheckoutService.php, app/Services/*Subscription*.php'
---

# App Services Services

## Platform webhook secrets are optional for checkout
Enabling platform Razorpay requires only the environment-matched Key ID and Key secret. A missing webhook secret must not block checkout, browser verification or reconciliation. Reject webhook delivery for credential versions without a webhook secret; never skip signature verification. Blank fields preserve configured secrets.

## Schedule paid package changes without blocking on unpaid attempts
Platform billing supports any eligible package. Preserve current terms and start an active subscription's next paid term at expiry; resolve by timestamps even if the scheduler is late. Only paid future terms block another future purchase. Unpaid/dismissed/expired attempts remain independently reconcilable; never resubmit an ambiguous attempt's order. Under central owner then payment locks, preserve conflicting or older late captures as resolution_required without replacing newer paid terms or refunding automatically.
