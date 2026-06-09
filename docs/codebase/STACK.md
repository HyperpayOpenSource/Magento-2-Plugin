# STACK

## Language & Runtime
- **PHP** (Magento 2 standard; no explicit version pinned in project root — inferred from Magento 2 module conventions)
- **JavaScript** (RequireJS / AMD modules, Magento 2 frontend stack)

## Framework
- **Magento 2** payment extension (`Hyperpay\Extension`)
  - Follows Magento 2 module structure: `etc/`, `Block/`, `Model/`, `Controller/`, `Helper/`, `view/`
  - Registered via `registration.php` and `etc/module.xml`
  - Payment methods extend `\Magento\Payment\Model\Method\AbstractMethod` (via `MethodAbstract`)

## Composer
- Manifest: `Hyperpay/Extension/composer.json` (module-level, not project root)
- No third-party PHP library dependencies beyond Magento 2 core

## Frontend
- **RequireJS** AMD modules in `view/frontend/web/js/`
- **jQuery** (version 3.3.1 loaded inline in `form.phtml` as `jq331` with `noConflict`)
- HyperPay **wpwl** (Web Payment Widget Library) — loaded dynamically via `$block->getFormUrl()`
- Payment form rendered server-side via `.phtml` template, with `<script>` blocks configuring `wpwlOptions`

## Admin
- Magento 2 admin config panel (`etc/adminhtml/system.xml`)
- No custom admin UI components — standard `system.xml` fields only

## Payment Brand / ACI Integration
- Uses **ACI Worldwide / HyperPay** hosted payment page (COPYandPAY widget)
- All API calls are server-side PHP cURL via `\Magento\Framework\HTTP\Client\Curl`
- Endpoints: configurable Test URL / Live URL (set in admin)
- SADAD (NCB): separate dedicated API endpoints (hardcoded in `Adapter.php`)

## Cron
- Magento cron job defined in `etc/crontab.xml` / `etc/cron_groups.xml`
- Cron class: `Cron/CancelOrderPending.php`

## i18n
- Translation CSV files: `i18n/ar_SA.csv`, `i18n/en_US.csv`

## Evidence
- `Hyperpay/Extension/composer.json`
- `Hyperpay/Extension/registration.php`
- `Hyperpay/Extension/etc/module.xml`
- `Hyperpay/Extension/view/frontend/web/js/view/payment/method-renderer.js`
- `Hyperpay/Extension/view/frontend/templates/form.phtml`
