# STRUCTURE

## Top-Level Layout
```
Hyperpay/Extension/          ← Magento 2 module root
  Api/                       ← PHP interfaces
  Block/                     ← Template block classes
  Controller/Index/          ← Frontend controllers (checkout callbacks)
  Cron/                      ← Scheduled job classes
  Helper/                    ← Helper utilities
  Model/                     ← Business logic models
    Method/                  ← One PHP class per payment method
    Source/                  ← Admin dropdown source models
  Observer/                  ← Magento event observers
  etc/                       ← XML configuration
    adminhtml/               ← Admin panel config (system.xml)
    frontend/                ← Frontend routes
  i18n/                      ← Translation CSV files
  view/
    adminhtml/               ← Admin view templates
    frontend/
      layout/                ← Layout XML (checkout_index_index.xml)
      templates/             ← PHP template files (form.phtml)
      web/
        images/              ← Payment method logos (SVG/PNG)
        js/view/payment/
          method-renderer.js ← Registers all payment method renderers
          method-renderer/   ← Individual renderer JS components
  registration.php           ← Module registration
  composer.json              ← Module composer metadata
docs/
  Hyperpay-magento2-Documentation.1.0.3.pdf
  codebase/                  ← Generated knowledge docs (this folder)
```

## Key Entry Points
| File | Purpose |
|------|---------|
| `registration.php` | Registers module with Magento autoloader |
| `etc/module.xml` | Declares module name and sequence |
| `etc/config.xml` | Default config values for all payment methods |
| `etc/adminhtml/system.xml` | Admin panel fields for all payment methods |
| `etc/di.xml` | Dependency injection preferences |
| `etc/events.xml` | Event observer registrations |
| `etc/webapi.xml` | REST API (webhook endpoint) |
| `Model/Adapter.php` | Core adapter — config reads, API calls, order/invoice logic |
| `Helper/Data.php` | Curl helpers, brand mapping, address building |
| `Block/Display.php` | Block for payment form page (after redirect) |
| `view/frontend/templates/form.phtml` | Main payment widget template |
| `view/frontend/layout/checkout_index_index.xml` | Injects payment methods into checkout |
| `view/frontend/web/js/view/payment/method-renderer.js` | Registers JS renderers |
| `Cron/CancelOrderPending.php` | Auto-cancels stale pending orders |
| `Model/MainConfigProvider.php` | Provides payment config data to checkout JS |

## Payment Method Models (`Model/Method/`)
All extend `MethodAbstract` which extends Magento's `AbstractMethod`.
Each class only sets `$_code`:

| Class | Code |
|-------|------|
| `Visa.php` | `HyperPay_Visa` |
| `Master.php` | `HyperPay_Master` |
| `Amex.php` | `HyperPay_Amex` |
| `Mada.php` | `HyperPay_Mada` |
| `CreditCard.php` | `HyperPay_CreditCard` |
| `Applepay.php` | `HyperPay_ApplePay` |
| `ApplepayTKN.php` | `HyperPay_ApplePayTKN` |
| `Stc.php` | `HyperPay_stc` |
| `Jcb.php` | `HyperPay_Jcb` |
| `ClickToPay.php` | `HyperPay_Click_to_pay` |
| `SadadNcb.php` | `HyperPay_SadadNcb` |
| `SadadPayware.php` | `HyperPay_SadadPayware` |
| `PayPal.php` | `HyperPay_PayPal` |
| `GooglePay.php` | `HyperPay_GooglePay` *(new)* |
| `SamsungPay.php` | `HyperPay_SamsungPay` *(new)* |

## Evidence
- Directory listing via scan output
- `Model/Method/` ls output
- `etc/config.xml`, `etc/adminhtml/system.xml`
