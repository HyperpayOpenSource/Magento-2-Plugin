# INTEGRATIONS

## ACI Worldwide / HyperPay Payment Gateway

### Integration Model
**COPYandPAY hosted widget** — HyperPay hosts the payment form. Merchant creates a checkout server-side, then loads the widget JS which handles card entry and submission.

### API Endpoints
| Setting | Config Key | Example |
|---------|-----------|---------|
| Test URL | `payment/hyperpay/testurl` | `https://eu-test.oppwa.com/v1/` |
| Live URL | `payment/hyperpay/liveurl` | `https://oppwa.com/v1/` |

### Authentication
- **Bearer Token** (`payment/hyperpay/auth`) — passed as `Authorization: Bearer <token>` header
- No OAuth; static token configured in Magento admin

### API Operations
| Operation | Method | Path | Used In |
|-----------|--------|------|---------|
| Create Checkout | POST | `/checkouts` | Controller (checkout initiation) |
| Get Payment Status | GET | `/payments/<checkoutId>` | Status controller |
| Capture (PA→CP) | POST | `/payments/<checkoutId>` | `MethodAbstract::capture()` |
| Refund | POST | `/payments/<checkoutId>` | `MethodAbstract::refund()` |
| Query by MerchantTxId | GET | `/query?entityId=&merchantTransactionId=` | `CancelOrderPending` |

### Request Parameters
- `entityId` — per-payment-method channel identifier
- `amount` — formatted to 2 decimals in live mode, integer in test mode
- `currency` — from per-method config
- `paymentType` — `DB` (debit), `PA` (pre-auth), `CP` (capture), `RF` (refund)
- `testMode=EXTERNAL` — appended in test mode

### ACI Result Codes
- **Success**: matches `/^(000\.400\.0|000\.400\.100)/` or `/^(000\.000\.|000\.100\.1|000\.[36])/`
- **No payment / not found**: `700.400.580` (triggers auto-cancel in cron)

### Webhook
- Endpoint: defined in `etc/webapi.xml` via `HookInterface`
- Webhook key: `payment/hyperpay/webhook_key`

### Google Pay (wpwl integration)
- `wpwlOptions.googlePay.gatewayMerchantId` = HyperPay entityId
- `wpwlOptions.googlePay.merchantId` = Google Merchant ID (live only, from `payment/HyperPay_GooglePay/google_merchant_id`)
- Configured networks: `AMEX, DISCOVER, JCB, MASTERCARD, VISA`
- Auth methods: `PAN_ONLY, CRYPTOGRAM_3DS`

### Samsung Pay (wpwl integration)
- Brand string: `SAMSUNGPAY`
- No extra `wpwlOptions` block needed — widget handles natively via brand

### Apple Pay
- `wpwlOptions.applePay.merchantCapabilities`: `["supports3DS"]`
- `wpwlOptions.applePay.supportedNetworks`: `["amex", "masterCard", "visa", "mada", "jcb"]`

## SADAD NCB (separate integration)
- Separate API (not ACI): `https://sadad.hyperpay.com/PayWareHub/api/PayWare/`
  - Test: `https://stg.sadad.hyperpay.com/PayWareHub/api/PayWare/`
- Operations: `SetCheckout`, `GetCheckoutStatus`
- Redirect: `https://sadad.hyperpay.com/PayWareHub/Pages/Checkout/Checkout.aspx?id=`

## Magento Internal
- `\Magento\Framework\HTTP\Client\Curl` — all outbound HTTP
- `\Magento\Sales\Model\Service\InvoiceService` — auto-invoice creation
- `\Magento\Sales\Api\OrderManagementInterface` — order cancel/notify
- `\Magento\Checkout\Model\Session` — read last real order
- `\Magento\Framework\View\Asset\Repository` — resolve payment logo URLs

## Evidence
- `Model/Adapter.php` (URLs, auth, API params)
- `view/frontend/templates/form.phtml` (wpwlOptions, googlePay block)
- `Cron/CancelOrderPending.php` (query endpoint, result code 700.400.580)
- `etc/adminhtml/system.xml` (google_merchant_id field)
