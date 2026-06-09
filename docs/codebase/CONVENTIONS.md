# CONVENTIONS

## Naming
- **Module namespace**: `Hyperpay\Extension`
- **Payment method codes**: `HyperPay_<BrandName>` (PascalCase brand, note mixed case prefix `HyperPay`)
- **Model classes**: PascalCase matching brand name (`GooglePay`, `SamsungPay`, `ClickToPay`)
- **Config keys**: snake_case (`payment_action`, `entity_id`, `google_merchant_id`, `cron_cancel`)
- **JS component names**: PascalCase (`DefaultPaymentMethods`, `SadadPayware`)
- **Constants in Adapter**: SCREAMING_SNAKE_CASE (`ENTITY_ID`, `GOOGLE_MERCHANT_ID`, `ACCESS_TOKEN`)

## Payment Method Registration Pattern
Adding a new payment method requires touching **exactly these 9 locations**:
1. `Model/Method/<Brand>.php` — new class extending `MethodAbstract`, only sets `$_code`
2. `etc/config.xml` — default config entry under `<payment>`
3. `etc/adminhtml/system.xml` — admin group with standard fields (title, active, payment_action, connector, entityId, currencycode, sort_order, allowspecific, specificcountry, cron_cancel)
4. `Model/MainConfigProvider.php` — add code to `$methodCodes` array
5. `view/frontend/layout/checkout_index_index.xml` — add method item with `isBillingAddressRequired: true`
6. `view/frontend/web/js/view/payment/method-renderer.js` — push renderer entry
7. `Helper/Data.php::getBrand()` — add switch case returning ACI brand string
8. `Helper/Data.php::getPaymentMarkImageUrl()` — add switch case returning logo asset URL
9. `Cron/CancelOrderPending.php::execute()` — add to `$methods` array

For **wallet methods** (GooglePay) add an extra location:
10. `Block/Display.php` + `form.phtml` — inject `wpwlOptions.<walletName>` block

## Error Handling
- Exceptions caught and re-thrown with order comment: `$order->addStatusHistoryComment('Exception message: '.$e->getMessage())`
- Helper methods use `$this->_messageManager->addError()` + `$this->_logger->critical()` for user-facing errors
- Cron uses `$this->logger->error()` and `continue` to skip individual order failures

## Formatting / Style
- PHP: 4-space indentation (with occasional tab inconsistencies in older code)
- PHP: Yoda conditions not used; standard `==` comparisons
- JS: 4-space indentation, `'use strict'`, RequireJS AMD pattern
- No linting config present in repository

## Imports / Use Statements
- PHP: fully-qualified class names inline (no `use` statements in most files); some files use `use \Magento\Sales\Model\Order as OrderStatus`
- JS: dependencies listed in `define([...], function(...))` array

## Config Path Pattern
- Shared HyperPay settings: `payment/hyperpay/<field>`
- Per-method settings: `payment/<method_code>/<field>`

## Evidence
- `Model/Method/GooglePay.php`, `Model/Method/SamsungPay.php` (new method pattern)
- `Model/Adapter.php` (constants, config access)
- `Cron/CancelOrderPending.php` (error handling pattern)
