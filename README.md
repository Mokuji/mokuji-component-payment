# Component: `payment`
Version 0.0.1 Alpha

_A payment handling component used for easy integration of payment systems into your components._

Supports the currencies:
* Euro

Supports the payment methods:
* iDeal
  - Rabobank OmniKassa

## Todo

.htaccess schrijven om de return php files te simuleren.

TX methods:
* Process TX callback / Set status
* Get status (from DB)
* Refresh status (no such thing for omnikassa though)
* is_refreshable() ??

Flow:
* Create TX
* Start TX
  - Externally handle TX
* Handle return
  - Claim TX
  - Store status