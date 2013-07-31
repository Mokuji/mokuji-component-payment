# Component: `payment`
Version 0.0.4 Alpha

_A payment handling component used for easy integration of payment systems into your components._

Supports the currencies:
* Euro

Supports the payment methods:
* PayPal
* iDeal
  - Rabobank OmniKassa

## Todo

TX methods:
* Process TX callback / Set status
* Refresh status (no such thing for omnikassa though)
* is_refreshable() ??

Flow:
* Create TX
* Start TX
  - Externally handle TX
* Handle return
  - Claim TX
  - Store status