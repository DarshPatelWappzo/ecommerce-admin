---
paths:
  - 'app/Services/PlatformGatewayConfigurationService.php, app/Services/PlatformRazorpayGateway.php'
---

# App Services Services

## Platform webhook secrets are optional for checkout
Enabling platform Razorpay requires only the environment-matched Key ID and Key secret. A missing webhook secret must not block checkout, browser verification or reconciliation. Reject webhook delivery for credential versions without a webhook secret; never skip signature verification. Blank fields preserve configured secrets.
