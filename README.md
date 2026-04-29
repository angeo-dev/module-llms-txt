# Angeo LLMs.txt — Magento 2

[![Packagist](https://img.shields.io/packagist/v/angeo/module-llms-txt.svg)](https://packagist.org/packages/angeo/module-llms-txt)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)

**Generates spec-compliant `llms.txt` and JSONL files for ChatGPT, Claude, Gemini, and Perplexity AI visibility.**

Part of the **[Angeo AI Commerce Suite](https://packagist.org/packages/angeo/)** — open-source Magento 2 modules for AI Engine Optimization (AEO).  
GitHub: [github.com/angeo-dev](https://github.com/angeo-dev) · Website: [angeo.dev](https://angeo.dev)

---

## Installation

```bash
composer require angeo/module-llms-txt
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## Usage

### CLI

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

**Stores → Configuration → Angeo → LLMs.txt** → click **Generate Now**.

### Cron

Runs automatically every day at 02:00 server time. Each store is emulated in `AREA_FRONTEND` context so that URLs and locale always resolve to correct frontend values.

```bash
bin/magento cron:run --group=default
```

---

## Generated files

Files are written to `pub/media/angeo/llms/` and served via a PHP controller:

| URL | File |
|-----|------|
| `yourstore.com/llms.txt` | `pub/media/angeo/llms/llms_default.txt` |
| `yourstore.com/llms.jsonl` | `pub/media/angeo/llms/llms_default.jsonl` |

Multi-store: each store gets its own file (`llms_en_us.txt`, `llms_de.txt`, etc.) served at the store's base URL.

---

## llms.txt format

Output follows the [llmstxt.org](https://llmstxt.org) spec — H1 title, metadata block, `##` sections with markdown links:

```
# My Store

> Store URL: https://mystore.com
> Currency: USD
> Locale: en-US

## Categories

- [All products](https://mystore.com/all-products.html)
- [Sale](https://mystore.com/sale.html)

## Products

- [Product name](https://mystore.com/product.html): 99.00 USD

## Pages

- [About us](https://mystore.com/about): About page description.
```

---

## Configuration

**Stores → Configuration → Angeo → LLMs.txt**

| Setting | Scope | Description | Default |
|---------|-------|-------------|---------|
| Enabled | Global | Enable/disable the module | Yes |
| Exclude This Store | Store View | Skip this store from generation | No |
| Include Products | Store View | Add `## Products` section | Yes |
| Include Categories | Store View | Add `## Categories` section | Yes |
| Include CMS Pages | Store View | Add `## Pages` section | Yes |
| Generate JSONL | Store View | Also generate `.jsonl` file | Yes |
| Product limit | Store View | Max products (0 = unlimited) | 5000 |

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

Implement `Angeo\LlmsTxt\Api\ProviderInterface`:

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

## The Angeo AI Commerce Suite

Free, MIT-licensed Magento 2 modules — [packagist.org/packages/angeo](https://packagist.org/packages/angeo/) · [github.com/angeo-dev](https://github.com/angeo-dev)

| Module | Packagist | Purpose |
|--------|-----------|---------|
| `angeo/module-aeo-audit` | [↗](https://packagist.org/packages/angeo/module-aeo-audit) | CLI AEO audit — 8 signals scored |
| `angeo/module-llms-txt` | [↗](https://packagist.org/packages/angeo/module-llms-txt) | **This module** — llms.txt + JSONL generator |
| `angeo/module-openai-product-feed` | [↗](https://packagist.org/packages/angeo/module-openai-product-feed) | ChatGPT Shopping CSV product feed |
| `angeo/module-openai-product-feed-api` | [↗](https://packagist.org/packages/angeo/module-openai-product-feed-api) | ACP REST API — 6 endpoints |
| `angeo/module-rich-data` | [↗](https://packagist.org/packages/angeo/module-rich-data) | JSON-LD schema — Product, Organization, FAQ, Breadcrumb |

Install the full suite:

```bash
composer require angeo/module-aeo-audit angeo/module-llms-txt angeo/module-openai-product-feed angeo/module-openai-product-feed-api angeo/module-rich-data
bin/magento setup:upgrade && bin/magento cache:flush
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

---

## License

MIT — see [LICENSE](LICENSE)
