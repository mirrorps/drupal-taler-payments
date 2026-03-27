# Taler Payments for Drupal

Developer-focused and site-builder-friendly documentation for the custom Drupal module that integrates GNU Taler payments.

This module adds a configurable Drupal block that starts a GNU Taler checkout flow, creates Merchant Backend orders on demand, opens the wallet with a `taler://` URI, and supports QR-based mobile payment.

## Who This Is For

This README is written for two audiences:

- Drupal site administrators and editors who want to place a Taler payment button on a page.
- Developers who need to install, configure, extend, test, or contribute to the module.

## Quick Start For Site Owners

If you only need to use the module on your Drupal site:

1. Install the module and its Composer dependencies.
2. Enable `taler_payments` from `Extend` or with Drush.
3. Open the settings page at `/admin/config/services/taler-payments`.
4. Save:
   - A Taler Base URL that includes the instance path and uses `https://`
   - Either an Access Token or Username/Password/Instance ID
5. Place the `Taler payment button` block in a region or layout.
6. Configure the block:
   - Button text
   - Summary
   - Amount
7. Save the block and visit the page where it appears.

## Requirements

- Drupal `^10 || ^11`
- PHP `>= 8.1`
- PHP OpenSSL extension (`ext-openssl`)
- Composer
- Reachable GNU Taler Merchant Backend over HTTPS
- The Composer package `mirrorps/taler-php`

## Installation

Install the module in your Drupal codebase and require its PHP dependencies with Composer.

Example from the Drupal project root:

```bash
composer require mirrorps/taler-php
drush en taler_payments -y
```

If the module already exists in `web/modules/custom/taler_payments`, make sure the project dependencies are installed:

```bash
composer install
```

## Configuration

Open the module settings page:

- URL: `/admin/config/services/taler-payments`
- Admin menu: `Configuration` -> `Web services` -> `Taler Payments`

The settings page is split into four sections.

### 1. Base URL

- Must be an absolute `https://` URL
- Must include the Merchant Backend instance path
- Example:
  `https://backend.demo.taler.net/instances/sandbox`

### 2. Access Token

- Enter the full Authorization header value
- Example:
  `Bearer secret-token:sandbox`
- The token is encrypted before it is stored
<br><br>** <i>Never commit or share a real token.</i>

### 3. Username And Password

- Requires:
  - Instance ID
  - Username
  - Password
- The password is encrypted before it is stored

### 4. Public Text Customization

Optional text overrides for the public checkout page:

- Call-to-action
- Thank you message
- Payment button CTA

If these values are left empty, the module uses safe defaults.

## Authentication Behavior

Two authentication methods are supported:

- Access token
- Username/password/instance ID

If both are configured, access token authentication takes priority.

## How To Show The Taler Payment Block

This is the main user-facing feature of the module.

### Place The Block In Standard Block Layout

1. Go to `Structure` -> `Block layout`.
2. Choose the region where you want the payment button to appear.
3. Click `Place block`.
4. Search for `Taler payment button`.
5. Click `Place block`.
6. Fill in the block settings:
   - `Button text`: text shown on the button
   - `Summary`: text describing the payment
   - `Amount`: GNU Taler amount format such as `EUR:1.00` or `KUDOS:0.50`
7. Save the block.
8. Save the block layout.

### Use The Block In Layout Builder

If your site uses Layout Builder, add the `Taler payment button` block the same way you would add any other custom block plugin to a section.

### Important Notes For Editors

- Each placed block instance has its own button text, summary, and amount.
- The amount must follow GNU Taler format: `CURRENCY:VALUE`
- Examples:
  - `EUR:1.00`
  - `KUDOS:0.11`
- When a visitor clicks the button, the order is created at that moment
- The visitor is redirected to a checkout page managed by this module

## What Visitors See

After clicking the payment button, the visitor is sent to a Drupal checkout page managed by this module.

The checkout page shows:

- A short call-to-action message
- The payment summary
- The amount
- The order ID
- A wallet button when a valid `taler://` URI is available
- A QR code for mobile wallet scanning
- Automatic payment status polling
- A thank-you message after successful payment

The module also adds the `taler` URI scheme to Drupal allowed protocols and outputs the Taler support meta tag on checkout pages.

## Core Behavior

- Block plugin: `Taler payment button`
- Orders are created only after user interaction
- Recent unpaid checkout intents may be reused briefly instead of creating duplicate orders immediately
- Checkout status is polled in the browser until a final state is reached

## Developer Notes

### Main Components

- `src/Plugin/Block/TalerPaymentButtonBlock.php`
  Drupal block plugin with per-instance settings.
- `src/TalerPaymentButtonBuilder.php`
  Builds the button render array and start-checkout link.
- `src/Controller/TalerCheckoutController.php`
  Handles checkout start, checkout page rendering, and status polling.
- `src/Checkout/TalerCheckoutManager.php`
  Creates orders, reuses recent intents, and maps Taler order status.
- `src/Service/TalerClientManager.php`
  Builds the configured Taler PHP SDK client.
- `src/Form/*`
  Admin forms for base URL, credentials, and public text customization.
- `src/PublicText/TalerPublicTextProvider.php`
  Resolves user-facing text with defaults and configuration overrides.

### Frontend Assets

- `js/taler-payment-button.js`
- `js/taler-checkout-polling.js`
- `css/taler-payment-button.css`
- `js/vendor/qrcodejs/qrcode.min.js`

### Configuration Storage

Settings are stored in Drupal configuration.

Sensitive credentials are encrypted before storage.

## Local Development

Install dependencies:

```bash
composer install
```

Enable the module in your Drupal environment:

```bash
drush en taler_payments -y
```

## Running Tests

This module currently includes unit tests.

From a Drupal project where the module is installed, run:

```bash
vendor/bin/phpunit web/modules/custom/taler_payments/tests/src/Unit
```

## Project Structure

- `config/install`
  Default module configuration
- `config/schema`
  Config schema definitions
- `src/Plugin/Block`
  Block plugin
- `src/Controller`
  Checkout and settings page controllers
- `src/Form`
  Admin settings forms
- `src/Checkout`
  Checkout state and Taler order orchestration
- `src/Service`
  Taler client bootstrap
- `src/Security`
  Credential encryption
- `src/PublicText`
  Public text defaults and overrides
- `src/Validation`
  Input validation helpers
- `js`
  Frontend behavior
- `css`
  Frontend styling
- `tests/src/Unit`
  Unit tests

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.


## Privacy

The module does not create user profiles.

When users trigger payment actions, Drupal sends order-related requests to the configured Merchant Backend. Hosting and backend logs may include metadata such as IP addresses and request details.

## License And Third-Party Components

- Module license: GPLv2 or later
- Bundled component: `js/vendor/qrcodejs/qrcode.min.js` (MIT License, davidshimjs)

See:

- `js/vendor/qrcodejs/LICENSE`

## Funding

This project is funded through [NGI TALER Fund](https://nlnet.nl/taler), a fund established by [NLnet](https://nlnet.nl) with financial support from the European Commission's [Next Generation Internet](https://ngi.eu) program. Learn more at the [NLnet project page](https://nlnet.nl/project/TalerPHP).

[<img src="https://nlnet.nl/logo/banner.png" alt="NLnet foundation logo" width="20%" />](https://nlnet.nl)
