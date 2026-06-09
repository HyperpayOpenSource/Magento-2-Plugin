# TESTING

## Frameworks
- **None detected** — no test framework config files found (`phpunit.xml`, `phpspec.yml`, `behat.yml`, etc.)
- No `tests/`, `spec/`, or `__tests__` directories present

## Manual Testing
- The ACI/HyperPay integration uses a test environment accessible via the admin-configured Test URL
- Test mode is set via `payment/hyperpay/mode` = `test`; this appends `&testMode=EXTERNAL` to API requests and disables SSL verification

## Test Cards (ACI Worldwide standard)
- ACI provides test card numbers per brand on their developer portal
- GooglePay test: uses Google's test card suite in the gpay sandbox environment
- SamsungPay test: requires Samsung Pay sandbox account

## Coverage Gaps
- No automated unit tests for any PHP classes
- No integration tests for ACI API communication
- No frontend JS tests
- `[TODO]` — confirm whether a separate test project exists outside this repo

## Evidence
- Scan output: no test directories or framework config found
- `Model/Adapter.php::getEnv()` returns `true` in test mode (used to skip SSL verification)
