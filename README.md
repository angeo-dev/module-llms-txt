# Angeo LLMs.txt — Magento 2

[![Packagist](https://img.shields.io/packagist/v/angeo/module-llms-txt.svg)](https://packagist.org/packages/angeo/module-llms-txt)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)

**Generates spec-compliant `llms.txt` and JSONL files for ChatGPT, Claude, Gemini, and Perplexity AI visibility.**

---

## What's new in v2.0.0

- **4 critical bugs fixed** — see Bug fixes section below
- **Spec-compliant format** — output now follows [llmstxt.org](https://llmstxt.org) with H1 title, `##` sections, and markdown links
- **`bin/magento angeo:llms:generate`** — CLI command (was missing in v1)
- **Files in `var/`** — moved from `pub/media/` (publicly browsable) to `pub/media/angeo/llms/` (served via PHP controller)
- **`AbstractGenerator`** — eliminates 95% duplicate code between `LlmsGenerator` and `JsonlGenerator`
- **Single `ProviderInterface`** — replaces two identical interfaces from v1
- **Per-store exception safety** — one failing store doesn't block others; errors logged
- **Admin config** — enable/disable per store, product limit, toggle JSONL generation

---

## Bug fixes (v1 → v2)

| Bug | Impact | Fix |
|-----|--------|-----|
| JSONL providers: `json_encode()` after `foreach` | Only the **last** category/product/page was encoded — all others silently dropped | Moved `json_encode()` inside loop; lines collected into array |
| `Cron` namespace triple-nested (`LlmsTxt\LlmsTxt\LlmsTxt\Cron`) | PHP fatal error on every cron run — cron never executed | Fixed to `Angeo\LlmsTxt\Cron` |
| `$output` initialized before store loop | Store N's file contained content from stores 1…N merged | Moved `$output = ''` inside the store loop |
| `Jsonl\CategoryProvider` missing root category filter | Returned system categories (ID 1, 2) and categories from all store views | Added `path LIKE 1/{rootId}/%` filter (same as Llms version) |

---

## Installation

```bash
composer require angeo/module-llms-txt
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## Usage

### CLI (recommended for CI/CD and first-time generation)

```bash
# Generate for all active stores
bin/magento angeo:llms:generate

# Generate for a specific store
bin/magento angeo:llms:generate --store=en_us

# Skip JSONL (llms.txt only)
bin/magento angeo:llms:generate --no-jsonl

# Skip llms.txt (JSONL only)
bin/magento angeo:llms:generate --no-llms
```

### Admin UI

Navigate to **Stores → Configuration → Angeo → LLMs.txt** and click **Generate Now**.

### Cron

Runs automatically every day at 02:00 server time. Verify your Magento cron is active:

```bash
bin/magento cron:run --group=default
```

---

## Generated files

Files are written to `pub/media/angeo/llms/` and served via a PHP controller:

| URL | File | Description |
|-----|------|-------------|
| `yourstore.com/llms.txt` | `pub/media/angeo/llms/llms_default.txt` | Spec-compliant llms.txt for AI crawlers |
| `yourstore.com/llms.jsonl` | `pub/media/angeo/llms/llms_default.jsonl` | JSONL for vector indexing pipelines |

For multi-store: each store gets its own file (`llms_en_us.txt`, `llms_de.txt`, etc.) served at the store's base URL.

---

## llms.txt format (llmstxt.org spec)

```
# EN

> Store URL: https://angeo.test
> Currency: USD
> Locale: en

## Categories

- [All products](https://angeo.test/all-products.html)
- [Sale](https://angeo.test/sale.html)

## Products

- [test](https://angeo.test/test1.html): 100.00 USD
- [test2](https://angeo.test/test2.html): 20.00 USD
- [test3](https://angeo.test/test3.html): 30.00 USD

## Pages

- [Home page](https://angeo.test/home): CMS homepage content goes here.
```

---

## Configuration

**Stores → Configuration → Angeo → LLMs.txt**

| Setting | Description | Default |
|---------|-------------|---------|
| Enabled | Enable/disable module | Yes |
| Include Products | Add `## Products` section | Yes |
| Include Categories | Add `## Categories` section | Yes |
| Include CMS Pages | Add `## Pages` section | Yes |
| Generate JSONL | Also generate `.jsonl` file | Yes |
| Product limit | Max products to include (0 = unlimited) | 5000 |

---

## Extending with custom providers

Register additional content sections via `di.xml`:

```xml
<type name="Angeo\LlmsTxt\Model\LlmsGenerator">
    <arguments>
        <argument name="providers" xsi:type="array">
            <item name="my_custom" xsi:type="object">Vendor\Module\Model\Llms\Providers\MyProvider</item>
        </argument>
    </arguments>
</type>
```

Your provider implements `Angeo\LlmsTxt\Api\ProviderInterface`:

```php
public function provide(StoreInterface $store): string
{
    return "## My Section\n\n- [Item](url): description\n\n";
}
```

---

## Testing

```bash
vendor/bin/phpunit -c app/code/Angeo/LlmsTxt/phpunit.xml
```

---

## The Angeo AI Suite

| Module | Purpose |
|--------|---------|
| `angeo/module-aeo-audit` | AEO audit — 8 signals scored |
| `angeo/module-llms-txt` | **This module** — llms.txt generator |
| `angeo/module-openai-product-feed` | CSV product feed |
| `angeo/module-openai-product-feed-api` | ACP REST API |

---

## License

MIT — see [LICENSE](LICENSE)
