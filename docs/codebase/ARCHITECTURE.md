# ARCHITECTURE

## Overview
This is a **Magento 2 payment gateway extension** integrating HyperPay (powered by ACI Worldwide) via the COPYandPAY hosted widget model. Each supported payment brand is a separate Magento payment method sharing one common `MethodAbstract` implementation.

## Layers

### 1. Config Layer
- `etc/config.xml` — default values per payment method (entityId, payment_action, currency, sort_order)
- `etc/adminhtml/system.xml` — admin panel fields (Token, URLs, per-method entityId, connector, Google Merchant ID)
- `Model/Adapter.php::getConfigData()` — reads `payment/hyperpay/<field>` (shared settings)
- `Model/Adapter.php::getConfigDataForSpecificMethod()` — reads `payment/<method_code>/<field>`

### 2. Payment Method Model Layer
- `Model/Method/MethodAbstract` extends `Magento\Payment\Model\Method\AbstractMethod`
  - Implements `capture()` — for PA (pre-auth) → CP (capture) flow via ACI API
  - Implements `refund()` — RF flow via ACI API
  - Uses `Adapter` + `Helper\Data` injected via constructor
- Each concrete method class (Visa, Master, etc.) only overrides `$_code`

### 3. Checkout Integration Layer
- `Model/MainConfigProvider` (implements `ConfigProviderInterface`) — injects payment logo URLs into Magento checkout JS config
- `view/frontend/layout/checkout_index_index.xml` — registers each method with `isBillingAddressRequired: true`
- `view/frontend/web/js/view/payment/method-renderer.js` — pushes all method codes into Magento's renderer list using `DefaultPaymentMethods` component (except SadadPayware which uses its own)

### 4. Payment Flow (COPYandPAY)
```
Customer selects payment → Place Order (Magento) →
  Observer (BeforeOrderPlaceObserver) fires →
  Controller calls ACI API to create Checkout (POST /checkouts) →
  ACI returns checkoutId → stored in order payment additionalInformation →
  Customer redirected to Display page →
  form.phtml loads wpwl widget from ACI script URL + checkoutId →
  Customer completes payment in widget →
  ACI redirects to shopperResultUrl (Magento status controller) →
  Controller queries ACI for result → orderStatus() updates Magento order
```

### 5. Adapter Layer (`Model/Adapter.php`)
Central hub for:
- Config reads (`getEntity`, `getConnector`, `getPaymentType`, `getAccessToken`, `getGoogleMerchantId`, etc.)
- URL resolution (test vs live mode)
- `orderStatus()` — maps ACI result codes to Magento order states
- `createInvoice()` — auto-creates Magento invoice on success
- SADAD-specific URLs

### 6. Helper Layer (`Helper/Data.php`)
- `getBrand()` — maps payment method code → ACI brand string (e.g. `GOOGLEPAY`, `VISA MADA`)
- `getCurlReqData()` / `getCurlRespData()` — cURL wrappers
- `getBillingAndShippingAddress()` — builds ACI request address params
- `shouldBlockMada()` — determines if MADA-blocking widget logic should activate
- `getPaymentMarkImageUrl()` / `getPaymentMarkImageUrlFromMultipleBrands()` — logo URL resolution

### 7. Frontend Display Layer
- `Block/Display.php` — template block for post-redirect payment page
  - `getPaymentBrand()`, `getFormUrl()`, `getShopperUrl()`, `getLang()`, `getStyle()`, `getCss()`
  - `shouldBlockMada()`, `getGoogleMerchantId()`, `getEntityId()` *(new in wallets_pay branch)*
- `view/frontend/templates/form.phtml` — renders wpwl widget with `wpwlOptions` JS object
  - Conditional blocks for: MADA blocking, Click-to-Pay, GooglePay, ApplePay notice

### 8. Cron Layer
- `Cron/CancelOrderPending.php` — queries ACI `/query?entityId=&merchantTransactionId=` per pending order, cancels or updates based on result code `700.400.580`

## ACI Result Code Pattern
Success: `/^(000\.400\.0|000\.400\.100)/` OR `/^(000\.000\.|000\.100\.1|000\.[36])/`
Pending (no payment found): `700.400.580`

## Key Design Decisions
- **One PHP class per payment brand** — zero logic duplication; all logic in `MethodAbstract`
- **All ACI communication is server-side** — no direct JS → ACI calls
- **Google Pay** uses wpwl widget's native GooglePay integration; `wpwlOptions.googlePay` is injected server-side from `getGoogleMerchantId()` / `getEntityId()`
- **MADA blocking** — client-side `onDetectBrand` callback prevents MADA cards on non-MADA forms

## Evidence
- `Model/Method/MethodAbstract.php`
- `Model/Adapter.php`
- `Helper/Data.php`
- `Block/Display.php`
- `view/frontend/templates/form.phtml`
- `Cron/CancelOrderPending.php`
