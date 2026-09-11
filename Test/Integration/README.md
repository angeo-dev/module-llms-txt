# Integration tests

These require a working Magento integration test framework
(`dev/tests/integration`) and a test database. They are **not** run by
`phpunit.xml`, which covers `Test/Unit` only, and they are **not** part of the
GitHub Actions workflow — that runs without a Magento installation.

Run them from the Magento root:

```bash
cd dev/tests/integration
../../../vendor/bin/phpunit --filter Angeo_LlmsTxt
```

`phpunit.xml.dist` in that directory must include this module's path in its
testsuite, or pass the file directly.
