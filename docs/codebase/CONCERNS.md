# CONCERNS

## Tech Debt

### 1. `CURLOPT_SSL_VERIFYPEER = false` in test mode
- **Location**: `Helper/Data.php::setCurlOptions()`, `Cron/CancelOrderPending.php:133`
- **Risk**: Comment says "this should be set to true in production" but test code disables peer verification entirely, and a developer using test credentials against a staging store would bypass SSL.

### 2. Inconsistent whitespace/indentation
- Several files mix tabs and spaces (`Model/Adapter.php:155`, `Model/Adapter.php:172`).
- Not a runtime bug but causes noise in diffs and violates PSR-2.

### 3. `$this->_objectManager->create(...)` direct usage
- **Location**: `Model/Adapter.php::orderStatus()` — creates `OrderCommentSender` via ObjectManager
- Magento best practice requires constructor injection; ObjectManager usage is a code smell and breaks testability.

### 4. `empty()` private method stub
- **Location**: `Helper/Data.php::getCreditCardBrandOptions()` — empty method body, never called.

### 5. Hardcoded SADAD URLs
- All four SADAD URLs are hardcoded string properties in `Adapter.php` rather than admin-configurable. Changes require code deployment.

### 6. No `[ASK USER]` — `HyperPay_SadadNcb` missing from cron methods
- `CancelOrderPending::$methods` does not include `HyperPay_SadadNcb`. This may be intentional (SADAD has its own flow) or an oversight.

## Security Risks

### 1. No input sanitization on address fields
- `Helper/Data.php::getBillingAndShippingAddress()` appends address fields directly to a query string without URL-encoding. Values like `&` in a street name would corrupt the ACI request.

### 2. Webhook key stored in plaintext
- `payment/hyperpay/webhook_key` stored in Magento `core_config_data` table — encrypted only if Magento's config encryption is configured.

### 3. `escapeJs()` used correctly for GooglePay
- `form.phtml:254,262` correctly uses `$block->escapeJs()` on merchant IDs. ✓

## Performance Bottlenecks

### 1. Cron: N+1 cURL calls per pending order
- `CancelOrderPending::execute()` fires one cURL call per pending order in a loop. High pending order volume → slow cron execution and potential API rate-limiting.

### 2. jQuery 3.3.1 loaded inline in `form.phtml`
- A full jQuery build is loaded on the payment result page from a CDN, separate from Magento's own jQuery. Adds ~87KB per page load.

## High-Churn Files (most-modified in recent history)
- `Block/Display.php`
- `Helper/Data.php`
- `view/frontend/templates/form.phtml`

These three files carry the most feature complexity and are the most likely to accumulate bugs.

## Current Branch (`wallets_pay`) — What Was Added
The branch adds **Google Pay** and **Samsung Pay** as new payment methods following the established pattern:
- New model classes: `GooglePay.php`, `SamsungPay.php`
- New SVG logos: `googlepay.svg`, `samsungpay.svg`
- All 9 standard registration touch-points updated
- Google Pay extra: `google_merchant_id` admin field, `getGoogleMerchantId()`/`getEntityId()` on Block, `wpwlOptions.googlePay` block in `form.phtml`
- MADA blocking logic also added in this branch (`shouldBlockMada()` in Helper and Block, JS in `form.phtml`)

## Evidence
- `Helper/Data.php:459` (SSL bypass in test)
- `Model/Adapter.php:453` (ObjectManager usage)
- `Helper/Data.php:565-568` (empty method)
- `Helper/Data.php:316-399` (unencoded address append)
- `Cron/CancelOrderPending.php:76-90` (methods list)
