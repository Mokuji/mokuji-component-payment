# Mokuji component: `payment`

* **Version:** 0.1.2 Beta
* **License:** GPLv3 (see [`LICENSE`](LICENSE))
* **Requires:** Mokuji 0.30.1-beta or higher

A **quick to implement** Mokuji component providing **multiple payment methods**.

Key features:

* Automatic transaction administration.
* One API for all payment methods. No need to learn platform specifics.
* Actively maintained and tested in real-life business applications.

New payment methods are added depending on demand for them.

## Current support

**Currencies:**

* Euro

**Payment methods:**

* PayPal
  - Express Checkout
* iDeal
  - Rabobank OmniKassa

## Maintainers

* Robin van Boven - https://github.com/Beanow
* Bart Roorda - https://github.com/2278766732

[Contributors list](https://github.com/Mokuji/mokuji-component-payment/graphs/contributors)

## How to contribute

To contribute, feel free to [submit issues](https://github.com/Mokuji/mokuji-component-payment/issues/new)
on the GitHub repository and the maintainers will respond to you.

If you want to submit code, please use the GitHub pull requests feature,
using the `develop` branch as the merge target for new features
or the `master` branch for bugfixes of the latest release.

## Which core problems does this component solve?

* Implementing payment methods take time as they require much research and coding every time.
* One-off implementations are rarely patched for bugs or security issues.
* Payment method implementations often do not re-use functionality they have in common.
* Maintaining a solid transaction record is challenging.
