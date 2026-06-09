# HyperPay Magento 2 Plugin — Developer Guide

This document is a handoff reference for the next developer. It covers what every file does and exactly what to touch when adding a new payment method.

---

## Plugin Structure

The entire plugin lives under `Hyperpay/Extension/`. Below is a directory-by-directory breakdown.

---

### `Model/Method/`

One PHP class per payment method. Every class is a stub that only sets `$_code`:

```php
class GooglePay extends MethodAbstract
{
    protected $_code = 'HyperPay_GooglePay';
}
```

The real logic (capture, refund) is inherited from `MethodAbstract.php`.

| File | Payment Method Code |
|---|---|
| `MethodAbstract.php` | Base class — shared `capture()` and `refund()` logic |
| `Visa.php` | `HyperPay_Visa` |
| `Master.php` | `HyperPay_Master` |
| `Mada.php` | `HyperPay_Mada` |
| `Amex.php` | `HyperPay_Amex` |
| `CreditCard.php` | `HyperPay_CreditCard` (multi-brand) |
| `Applepay.php` | `HyperPay_ApplePay` |
| `ApplepayTKN.php` | `HyperPay_ApplePayTKN` |
| `GooglePay.php` | `HyperPay_GooglePay` |
| `SamsungPay.php` | `HyperPay_SamsungPay` |
| `Stc.php` | `HyperPay_stc` |
| `Jcb.php` | `HyperPay_Jcb` |
| `ClickToPay.php` | `HyperPay_Click_to_pay` |
| `PayPal.php` | `HyperPay_PayPal` |
| `SadadNcb.php` | `HyperPay_SadadNcb` |
| `SadadPayware.php` | `HyperPay_SadadPayware` (separate Sadad flow) |
| `Valu.php` | `HyperPay_Valu` |

---

### `Model/Adapter.php`

The central service class. It holds all API-level logic:

- Reads all config values from `payment/hyperpay/*` and `payment/<MethodCode>/*`
- Builds URLs for test vs live mode
- `orderStatus()` — evaluates the HyperPay result code and sets Magento order state to processing or cancelled
- `createInvoice()` — auto-creates a Magento invoice on successful payment
- `buildCaptureOrRefundRequest()` — assembles the POST body for capture/refund API calls
- `setInfo()` / `getCheckoutId()` — persists the HyperPay `checkoutId` in `sales_order_payment.additional_information`

---

### `Model/MainConfigProvider.php`

Implements `ConfigProviderInterface`. This is what injects payment method data into the frontend checkout JS config.

- Contains `$methodCodes` — the master list of all active method codes.
- For each code it exposes `paymentAcceptanceMarkSrc` (the logo image URL) to the checkout JS.
- **Every new method must be added to `$methodCodes` here** or it will be ignored by the frontend.

---

### `Model/Hook.php` / `Api/HookInterface.php`

Handles incoming webhook notifications from HyperPay. The `HookInterface` is bound to `Hook` via `etc/di.xml`. Processes server-to-server payment status updates.

---

### `Model/Source/`

Drop-down option providers for the admin config form:

| File | Used for |
|---|---|
| `Mode.php` | Test / Live mode selector |
| `Style.php` | Form style (card, plain, none) |
| `PaymentAction.php` | DB (debit) / PA (pre-auth) |
| `Connectors.php` | Connector selector (MIGS, etc.) |
| `CreditCardOptions.php` | Multi-brand picker for CreditCard method |
| `BlackBins.php` | List of card BINs that must redirect to MADA |

---

### `Helper/Data.php`

Utility class injected everywhere. Key responsibilities:

- `getBrand()` — maps a method code (e.g. `HyperPay_GooglePay`) to the HyperPay `data-brands` string (e.g. `GOOGLEPAY`) sent to the payment widget.
- `getPaymentMarkImageUrl()` — resolves the logo image URL for checkout display.
- `getCurlReqData()` / `getCurlRespData()` — all HTTP calls to HyperPay API go through here.
- `shouldBlockMada()` — detects card BINs that must be redirected to MADA.
- `getCurrentOrder()` — returns the current order from session.

---

### `Block/`

| File | Purpose |
|---|---|
| `Display.php` | Provides data to `form.phtml` — brand string, widget script URL, shopper redirect URL, language, CSS, Google Merchant ID |
| `Status.php` | Used by status result pages |
| `Adminhtml/Order/View/Custom.php` | Adds HyperPay transaction data to the admin order view |

---

### `Controller/Index/`

| File | Route | Purpose |
|---|---|---|
| `Request.php` | `hyperpay/index/request` | Initiates the checkout: calls HyperPay API to create a checkout session, saves `checkoutId`, renders the payment widget |
| `Status.php` | `hyperpay/index/status` | Return URL for most methods — verifies payment result with HyperPay, updates order, redirects to success or failure |
| `Sstatus.php` | `hyperpay/index/sstatus` | Return URL specifically for Sadad Payware |
| `Sadad.php` | `hyperpay/index/sadad` | Handles Sadad NCB flow |

---

### `Cron/CancelOrderPending.php`

A scheduled job. Cancels orders that have been stuck in `pending_payment` longer than the configured timeout (admin → `cancel_order` field in minutes).

---

### `Observer/BeforeOrderPlaceObserver.php`

Fires before an order is placed. Used to validate or modify order data at placement time (e.g. currency checks).

---

### `etc/`

| File | Purpose |
|---|---|
| `module.xml` | Declares the module name and version |
| `config.xml` | Default values for every payment method (entity ID placeholder, default currency SAR, model class binding) |
| `adminhtml/system.xml` | Admin UI — one `<group>` block per payment method containing all its config fields |
| `di.xml` | Binds `HookInterface` → `Hook` model |
| `frontend/di.xml` | Frontend DI overrides |
| `frontend/routes.xml` | Declares the `hyperpay` frontend route |
| `webapi.xml` | Exposes the webhook endpoint via REST API |
| `events.xml` | Registers `BeforeOrderPlaceObserver` |
| `crontab.xml` / `cron_groups.xml` | Registers the pending-order cancellation cron job |
| `csp_whitelist.xml` | Content Security Policy — whitelists HyperPay script/connect domains |

---

### `view/frontend/`

| Path | Purpose |
|---|---|
| `layout/checkout_index_index.xml` | Wires each method code into the Magento checkout JS component tree; required so the checkout renders the payment tab |
| `web/js/view/payment/method-renderer.js` | Registers each method code with a JS renderer component |
| `web/js/view/payment/method-renderer/DefaultPaymentMethods.js` | The shared Knockout JS component for almost all methods |
| `web/js/view/payment/method-renderer/SadadPayware.js` | Custom renderer for Sadad Payware (different flow) |
| `web/template/payment/hyperpay.html` | Knockout HTML template for the checkout payment tab |
| `templates/form.phtml` | The hosted payment widget page — loads the HyperPay JS script, renders the `<form class="paymentWidgets">` |
| `templates/status.phtml` | Result/status page template |
| `web/images/` | Logo SVG/PNG files, one per method |

---

### `i18n/`

Translation files: `ar_SA.csv` and `en_US.csv`. Add translated strings here when adding UI labels.

---

## Adding a New Payment Method

Adding a new method requires touching **7 files** in a specific order. All are mechanical changes following the same pattern used by every existing method.

### Step 1 — Create the Model class

**File:** `Model/Method/YourMethod.php`

Copy any existing stub (e.g. `GooglePay.php`) and change the code:

```php
namespace Hyperpay\Extension\Model\Method;

class YourMethod extends \Hyperpay\Extension\Model\Method\MethodAbstract
{
    protected $_code = 'HyperPay_YourMethod';
}
```

**Why:** Magento's payment system requires a model class. The code `HyperPay_YourMethod` is the unique identifier used everywhere else.

---

### Step 2 — Register default config

**File:** `etc/config.xml`

Add a new block inside `<payment>`:

```xml
<HyperPay_YourMethod>
    <title>Your Method</title>
    <model>Hyperpay\Extension\Model\Method\YourMethod</model>
    <active>0</active>
    <payment_action>DB</payment_action>
    <currencycode>SAR</currencycode>
    <entityId>Enter your Entity Id</entityId>
    <connector>migs</connector>
    <cron_cancel>0</cron_cancel>
    <sort_order>13</sort_order>
</HyperPay_YourMethod>
```

**Why:** Without this, Magento won't know the model class to load or what defaults to use. The `<model>` tag is how Magento resolves your class.

---

### Step 3 — Add admin config fields

**File:** `etc/adminhtml/system.xml`

Add a `<group>` block inside `<section id="payment">`. Copy the block from an existing method like `HyperPay_GooglePay` and change the `id` and `<label>`:

```xml
<group id="HyperPay_YourMethod" translate="label" type="text" sortOrder="2"
       showInDefault="1" showInWebsite="1" showInStore="1">
    <label>HyperPay Your Method</label>
    <!-- copy all <field> blocks from an existing method -->
</group>
```

**Why:** This creates the settings section in Stores → Configuration → Payment Methods so merchants can enable the method and enter their entity ID.

---

### Step 4 — Register in MainConfigProvider

**File:** `Model/MainConfigProvider.php`

Add to the `$methodCodes` array:

```php
protected $methodCodes = [
    // ... existing entries ...
    'HyperPay_YourMethod',
];
```

**Why:** This is the list the config provider iterates to expose the logo image URL to the frontend checkout JS. Without this entry the checkout page won't know the method exists.

---

### Step 5 — Map the brand string

**File:** `Helper/Data.php` — inside `getBrand()` switch statement

```php
case 'HyperPay_YourMethod':
    $paymentMethod = 'YOUR_BRAND_STRING';
    break;
```

**Why:** The `data-brands` attribute on the HyperPay widget form must match the exact brand string HyperPay expects (e.g. `GOOGLEPAY`, `SAMSUNGPAY`). Without this case, the widget won't know which card/wallet type to render.

---

### Step 6 — Register the JS renderer

**File:** `view/frontend/web/js/view/payment/method-renderer.js`

Add an entry to `rendererList.push(...)`:

```js
{
    type: 'HyperPay_YourMethod',
    component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
},
```

Use `DefaultPaymentMethods` for standard HyperPay-hosted widget methods. Use `SadadPayware` only for Sadad Payware's distinct redirect flow.

**Why:** Magento's checkout JS needs an explicit mapping from method code to UI component or it won't render a payment tab for this method.

---

### Step 7 — Wire into the checkout layout

**File:** `view/frontend/layout/checkout_index_index.xml`

Add an `<item>` inside the `<item name="methods">` block:

```xml
<item name="HyperPay_YourMethod" xsi:type="array">
    <item name="isBillingAddressRequired" xsi:type="boolean">true</item>
</item>
```

**Why:** This XML is how Magento's layout system passes the method configuration into the checkout JS component tree. Without it the renderer registered in Step 6 will not be activated.

---

### Optional: Add a logo

**File:** `view/frontend/web/images/yourmethod.svg` (or `.png`)

The logo file name is referenced by `Helper/Data.php → getPaymentMarkImageUrl()`, which maps method codes to image paths. Check that method to verify the expected filename.

---

## Summary Checklist

| # | File | What to add |
|---|---|---|
| 1 | `Model/Method/YourMethod.php` | New class extending `MethodAbstract`, set `$_code` |
| 2 | `etc/config.xml` | Default config block with model class reference |
| 3 | `etc/adminhtml/system.xml` | Admin config group for merchant settings |
| 4 | `Model/MainConfigProvider.php` | Add code to `$methodCodes` array |
| 5 | `Helper/Data.php` | Add `case` in `getBrand()` switch |
| 6 | `view/frontend/web/js/view/payment/method-renderer.js` | Add `rendererList` entry |
| 7 | `view/frontend/layout/checkout_index_index.xml` | Add method item in layout XML |
| *(opt)* | `view/frontend/web/images/` | Add logo image |

After making all changes, run `bin/magento setup:upgrade && bin/magento cache:flush` to pick up the new config and DI changes.
