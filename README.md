# Angeo LLMs.txt — Magento 2 Module

**AI Engine Optimization (AEO) for Magento 2 / Adobe Commerce.** Generates
spec-compliant `llms.txt`, `llms-full.txt`, and JSONL files so ChatGPT,
Claude, Gemini, Perplexity, and other LLM-powered crawlers can ingest your
catalog efficiently.

[![Magento](https://img.shields.io/badge/Magento-2.4.7%2B-orange)]()
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-blue)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()
[![Hyvä](https://img.shields.io/badge/Hyv%C3%A4-natively%20compatible-brightgreen)]()

> **Current version: 4.3.0** — fixes an empty blockquote in the generated
> files, finishes the `Sitemap:` line in agents.md, and adds router integration
> tests. No breaking changes.
>
> **4.2.0** — reads salable status through Multi-Source
> Inventory when MSI is installed, so availability is correct on stores with
> more than one stock. No breaking changes.
>
> **4.1.0** — operational release on top of 4.0.0: skip a
> store's catalog pass when nothing changed, per-entity markdown mirror
> invalidation on save, and an `angeo:llms:clean` command. No breaking changes
> from 4.0.0.
>
> **4.0.0** does two things: it carries out the
> deprecations announced in 3.2.0, and it brings the module in line with
> **llms.txt v2** (llmstxt.org, 10 August 2026) — link relations, links that
> point at markdown versions of pages, and the end of `## Optional` as a
> mechanical instruction. It also adds availability, image and attribute
> export for AI shopping agents, policy links in `agents.md`, and an agentic
> discovery sitemap. **It contains breaking changes** — read the upgrade notes
> in [CHANGELOG.md](CHANGELOG.md) before deploying, especially if any other
> module extends the generated files.

---

## What this module does

After install, your storefront serves:

| URL                          | What it is                                         |
| :--------------------------- | :------------------------------------------------- |
| `https://shop/llms.txt`      | Spec-compliant llmstxt.org file (compact markdown) |
| `https://shop/llms-full.txt` | Same structure, full sanitized descriptions inline |
| `https://shop/llms.jsonl`    | One JSON record per line, for vector indexing      |
| `https://shop/{url-key}.md`  | On-the-fly Markdown mirror of any product/category/CMS page |
| `https://shop/agents.md`     | Operator's manual for AI agents: rules, policies, surfaces |
| `https://shop/sitemap_agentic_discovery.xml` | Sitemap declaring the files above |

Product, category and CMS pages also carry the llms.txt v2 link relations in
their `<head>`, so an agent holding a page URL can find both its markdown
version and the llms.txt describing it:

```html
<link rel="alternate" type="text/markdown" href="https://shop/blue-shirt.html.md"/>
<link rel="describedby" href="https://shop/llms.txt"/>
```

Both markdown URL forms resolve: `page.html.md` (v1) and `page.md` (added in
v2). The mirror controller matches either against `url_rewrite`, with or
without your store's URL suffix.

Generation happens via cron (daily by default), CLI, or the admin "Generate
Now" button. The output is streamed to disk with bounded memory, atomically
renamed on completion, and served with proper `ETag` / `Cache-Control` headers.

---

## Why this module exists

LLM crawlers can ingest a typical Magento storefront — full theme, JS, image
sprites, navigation chrome — but that's wasteful for everyone. The
[llmstxt.org](https://llmstxt.org) standard defines a clean text format
optimized for AI ingestion: stable links, structured headings, descriptions
in their natural prose form rather than buried in product cards.

This module produces that format for Magento, with care taken for the things
Magento makes hard: multi-store layout, Page Builder content, CMS directive
resolution, customer-group pricing, and very large catalogs.


### A note on the spec

This module follows [llmstxt.org](https://llmstxt.org) **v2** (10 August 2026).
Two things are worth stating plainly, because they are commonly misreported:

* **`llms-full.txt` is not part of the specification.** It is a convention
  popularised by Mintlify. The spec keeps llms.txt small and puts the detail
  behind links. The format is supported here because it is genuinely useful
  for smaller catalogues, but enable it knowingly — it is 5–50× larger and no
  crawler is obliged to understand it.
* **There is no W3C standard for llms.txt.** Several articles describe a
  "W3C working draft" that forbids llms.txt at subpaths and requires a version
  header. No such draft exists — only an open strategy issue from 2025 — and
  v2 explicitly *permits* subpath files, with the most specific one winning.
  That is what makes per-store-code files such as `/de/llms.txt` valid.

---

## Installation

```bash
composer require angeo/module-llms-txt:^4.0
bin/magento module:enable Angeo_LlmsTxt
bin/magento setup:upgrade
bin/magento setup:di:compile      # only in production mode
bin/magento setup:static-content:deploy   # only in production mode
bin/magento cache:flush
```

Then generate your first batch:

```bash
bin/magento angeo:llms:generate
```

Visit `https://your-store.tld/llms.txt`.

---

## Configuration reference

All settings live at **Stores → Configuration → Angeo → LLMs.txt**.

### General

| Field             | Default | Notes                                                             |
| :---------------- | :------ | :---------------------------------------------------------------- |
| **Enable**        | Yes     | Master switch.                                                    |
| **Exclude This Scope** | No | Available at website + store scope. Skips generation for this scope. |
| **Store Summary** | —       | One-line summary used as the spec-compliant blockquote. If empty, falls back to *Design → HTML Head → Default Description*. |
| **Attribution Signature** | Yes | One-line `Generated by Angeo LlmsTxt …` markdown footer on `llms.txt` / `llms-full.txt` / `agents.md` / `.md` mirrors (never in JSONL). Credits the free module; feel free to disable — nothing else changes. |
| **Generate agents.md** | Yes | Operator's manual for AI agents at `/agents.md`: interaction rules + links to the store's machine-readable surfaces (UCP profile and MCP endpoint auto-linked when `angeo/module-ucp` / `angeo/module-mcp-server` are installed). Also appends a "For Agents & Developers" section to llms.txt. |

### Content

| Field                                | Default | Notes                                                  |
| :----------------------------------- | :------ | :----------------------------------------------------- |
| **Include Categories**               | Yes     |                                                        |
| **Include CMS Pages**                | Yes     |                                                        |
| **Include Products**                 | Yes     |                                                        |
| **Products under `## Optional`**     | **No**  | Changed in 4.0.0. llms.txt v2 removed the mechanical meaning of `## Optional` — it no longer tells any tool what to drop, it is only a convention for secondary links. Products are a store's primary content, so they get their own `## Products` section. |
| **Product Limit**                    | 5000    | 0 = unlimited.                                         |
| **Exclude Out-of-Stock Products**    | No      |                                                        |
| **CMS Identifiers to Exclude**       | `no-route, enable-cookies, privacy-policy-cookie-restriction-mode` | Comma- or newline-separated. |
| **Customer Group for Pricing**       | NOT LOGGED IN | Which group's final price (with special / group prices) is exposed. |

### Output formats

| Field                          | Default | Notes                                                 |
| :----------------------------- | :------ | :---------------------------------------------------- |
| **Generate llms.txt**          | Yes     |                                                       |
| **Generate llms-full.txt**     | No      | 5–50× larger; enable only if you actually want it.    |
| **Generate JSONL**             | Yes     | One record per line; embeds-ready.                    |
| **Serve `/url-key.md` Mirrors**| No      | Per-entity Markdown rendering; on-the-fly, no disk.   |
| **Generate agents.md**         | Yes     | Operator's manual for AI agents.                      |
| **Emit Link Relations**        | Yes     | llms.txt v2 discoverability: `rel="alternate" type="text/markdown"` and `rel="describedby"` in `<head>`, plus a `Link:` header on the mirrors. Requires mirrors. |
| **Link to Markdown Mirrors in llms.txt** | Yes | v2 asks that llms.txt links lead to LLM-friendly content, so entity links use the `.md` URL. JSONL keeps `url` and adds `md_url` beside it. Requires mirrors. |
| **Serve Agentic Discovery Sitemap** | Yes | `/sitemap_agentic_discovery.xml`, listing only the formats actually enabled for the store. |

### Product data

Availability and price are the two facts an AI shopping agent needs before it
recommends anything. Before 4.0.0 the module exported only price.

| Field                       | Default | Notes                                                   |
| :-------------------------- | :------ | :------------------------------------------------------ |
| **Include Availability**    | Yes     | `in_stock` in JSONL, *In stock / Out of stock* in the markdown formats. Loaded in batch per collection page — no query per product. |
| **Availability Source**     | Auto    | `Auto` reads salable status through MSI when installed, the legacy stock index otherwise. Matters only if you run more than one stock — with a single Default Stock both answers are identical. `Legacy` forces the old index. |
| **Include Image URL**       | Yes     | Absolute URL of the base image (original file, not a resized cache variant). |
| **Brand Attribute Code**    | —       | e.g. `manufacturer`. Exported under the dedicated `brand` key so agents need not guess which attribute carries it. |
| **Additional Attributes**   | —       | Comma- or newline-separated codes, e.g. `color, size, material`. Dropdown values export as store-view labels, not option IDs. Empty values are dropped per product. Keep the list short — each code adds a column to every collection page. |

### Agents.md content

Each field takes an absolute URL or a path relative to the store base URL, so
a CMS page identifier such as `shipping-policy` works as-is. They render as a
`## Key pages` section in `agents.md`; the section is omitted entirely when
nothing is configured, so the file never carries an empty heading.

| Field | Notes |
| :---- | :---- |
| **Delivery / Returns / Privacy / Terms / About / Support** | The policy pages an agent should quote rather than summarise from memory. |

### Content sanitization

| Field                            | Default | Notes                                                 |
| :------------------------------- | :------ | :---------------------------------------------------- |
| **Resolve CMS Directives**       | Yes     | Renders `{{widget}}`, `{{block}}`, `{{var}}` via Magento's frontend filter. |
| **Page Builder Strategy**        | Exclude | See below.                                            |
| **Excluded Content-Types**       | `products, banner, slider, slide, video, map, buttons, button-item, block, dynamic-block, divider, spacer` | Used under *Exclude* strategy. |
| **Allowed Content-Types**        | `text, heading, html, tabs, tab-item, row, column, column-group` | Used under *Allow* strategy. |

#### Page Builder strategies

| Strategy | Effect                                                                   |
| :------- | :------------------------------------------------------------------------ |
| **Preserve** | Keep all Page Builder content; only strip wrapper attributes.        |
| **Exclude**  | Drop elements whose `data-content-type` is in the excluded list. **Default.** |
| **Allow**    | Drop everything EXCEPT `data-content-type` in the allowed list.       |
| **Strip**    | Drop ALL elements that carry a `data-content-type` attribute.         |

The filter parses content with `DOMDocument` (not regex), so nested Page
Builder containers are handled correctly. Known content-types include:
`row`, `column-group`, `column`, `tabs`, `tab-item`, `text`, `heading`,
`html`, `image`, `video`, `map`, `divider`, `spacer`, `buttons`, `button-item`,
`banner`, `slider`, `slide`, `products`, `block`, `dynamic-block`.

### Performance

| Field                       | Default | Notes                                                   |
| :-------------------------- | :------ | :------------------------------------------------------ |
| **Skip Generation When Nothing Changed** | **No** | Skips a store's whole catalog pass when its products, categories, CMS pages and module config all pre-date the last successful run. Off by default — see the caveat below. |
| **Invalidate Markdown Mirror on Save** | Yes | Drops an entity's cached `.md` mirror when it is saved or deleted, like Magento does for the cached HTML page. Costs one `url_rewrite` lookup per save. |
| **Collection Page Size**    | 500     | Products loaded per collection page while streaming. Lower if you hit memory limits; raise only on hosts with generous PHP memory. |

> **Read this before enabling *Skip Generation When Nothing Changed*.**
> Change detection reads entity timestamps. It therefore does **not** see
> stock movements — `cataloginventory_stock_item` has no timestamp column —
> nor prices changed by catalog price rules or scheduled updates, which do not
> touch the product row. Since 4.0.0 exports availability, a store with moving
> stock would publish stale in-stock flags. Enable it only if your catalog
> changes through product saves. `angeo:llms:generate --force` always rebuilds,
> and if detection fails for any reason the store is regenerated rather than
> skipped.

### HTTP caching

| Field                       | Default | Notes                                                   |
| :-------------------------- | :------ | :------------------------------------------------------ |
| **Cache-Control TTL (s)**   | 3600    | Sent as `public, max-age=…` on the served files.        |

### Cron

| Field                | Default        | Notes                                                  |
| :------------------- | :------------- | :----------------------------------------------------- |
| **Cron Expression**  | `0 2 * * *`    | Daily at 02:00 server time.                            |

---

## CLI commands

```bash
# Generate everything for all eligible stores
bin/magento angeo:llms:generate

# Single store, skip JSONL
bin/magento angeo:llms:generate --store=default --no-jsonl

# Rebuild even when nothing changed since the last run
bin/magento angeo:llms:generate --force

# Delete generated files and flush the .md mirror cache
bin/magento angeo:llms:clean --store=default

# Per-store/per-format last-run status
bin/magento angeo:llms:status

# Lint generated files against the llms.txt v2 spec
bin/magento angeo:llms:validate

# Same, but warnings fail the build — use this in CI
bin/magento angeo:llms:validate --strict
```

`validate` checks the H1, at most one blockquote summary, that no heading sits
between the summary and the first H2 (v2: "sections of any type except
headings"), that every section list item is a real markdown link with an
absolute URL, and that JSONL holds exactly one JSON object per line. It warns
when llms.txt links point at HTML pages while you are serving markdown
mirrors.

---

## Extending — custom entity providers

Add your own section to the generated files (a "Brands" list, blog posts, a
store locator) by implementing `Angeo\LlmsTxt\Api\EntityProviderInterface` and
registering it on the pipeline.

The pipeline reads the catalog **once per store** and renders every enabled
format from that single pass. So a provider does not emit markdown or JSON —
it yields format-agnostic `EntityRecordInterface` records, and the renderers
turn each record into llms.txt, llms-full.txt and JSONL. You write the data
extraction once and get all three formats.

```php
namespace Vendor\Module\Provider;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\EntityProviderInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Data\EntityRecord;

class BrandProvider implements EntityProviderInterface
{
    public function isApplicable(OutputContextInterface $context): bool
    {
        return true;
    }

    public function provide(OutputContextInterface $context): iterable
    {
        // Must be memory-bounded: yield as you page, never build an array.
        foreach ($this->brandRepo->getList((int) $context->getStore()->getId()) as $brand) {
            yield new EntityRecord(
                type:     EntityRecordInterface::TYPE_CATEGORY,
                entityId: (int) $brand->getId(),
                name:     (string) $brand->getName(),
                url:      (string) $brand->getUrl(),
                content:  (string) $brand->getDescription(),
            );
        }
    }
}
```

```xml
<!-- etc/di.xml -->
<type name="Angeo\LlmsTxt\Model\Pipeline\SinglePassGenerator">
    <arguments>
        <argument name="entityProviders" xsi:type="array">
            <item name="brands" xsi:type="object" sortOrder="50">Vendor\Module\Provider\BrandProvider</item>
        </argument>
    </arguments>
</type>
```

Order matters: the bundled `StoreEntityProvider` must stay first, because it
carries the H1 and the blockquote summary.

Two things to know about the records:

* **Content is sanitized by you, once.** `getContent()` and
  `getShortContent()` must already be plain text at the longest length any
  format needs; renderers only truncate downwards. Inject
  `Angeo\LlmsTxt\Api\SanitizerInterface` and run your HTML through it.
* **`EntityRecord` takes optional trailing arguments** —
  `inStock`, `imageUrl` and `attributes` (all added in 4.0.0). Named
  arguments, as above, are the safe way to construct it.

To render a record differently, implement
`Angeo\LlmsTxt\Api\FormatRendererInterface` and replace the renderer for that
format in the `renderers` argument of the same `di.xml` type.

### Migrating from the 3.x `ProviderInterface`

`Api\ProviderInterface`, `Model\Provider\AbstractProvider` and the three
format generators were removed in 4.0.0, together with the compatibility pass
that kept them running. A module still registering a provider on
`Model\Generator\LlmsTxtGenerator` (or its siblings) will fail at
`bin/magento setup:di:compile` with the missing class name.

| 3.x                                       | 4.0.0                                                  |
| :---------------------------------------- | :----------------------------------------------------- |
| `Api\ProviderInterface`                    | `Api\EntityProviderInterface`                           |
| `extends Model\Provider\AbstractProvider`  | `implements EntityProviderInterface` (no base class)    |
| `yield "## Brands\n\n"` — format strings   | `yield new EntityRecord(...)` — one record per entity   |
| One provider per format (three classes)    | One provider, rendered into every format                |
| `di.xml` → `LlmsTxtGenerator.providers`    | `di.xml` → `SinglePassGenerator.entityProviders`        |
| `escapeMarkdown()` / `encodeJsonl()` from the base class | Handled by the renderers; you no longer format output |

Section headers are emitted by the renderers based on `getType()`, so drop
your own `## Heading` yields — otherwise they will appear twice.

---

## Hyvä

**Natively compatible. No compatibility module, no theme override, no
Tailwind rebuild.**

The module ships no frontend JavaScript, no CSS and no LESS. Everything it
serves is either a plain-text file rendered by a controller (`llms.txt`,
`llms-full.txt`, `llms.jsonl`, `agents.md`, the `.md` mirrors, the agentic
sitemap) or two `<link>` tags in the document head. None of that touches the
theme layer, which is where Luma and Hyvä differ.

Specifically:

* The only frontend template, `head/link_relations.phtml`, emits two `<link>`
  elements. No jQuery, no Knockout, no `x-magento-init`, no `data-mage-init`,
  no CSS classes. It renders identically under both themes, so there is no
  `hyva_` layout variant and no Hyvä-specific template in this module.
* The three layout files attach to `head.additional`, which Hyvä keeps as an
  extension point — Hyvä's own theme uses it to inject `hyva.phtml`.
* Nothing needs to be added to `hyva-themes.json`. That registration exists so
  a theme can scan a module's CSS for Tailwind classes; this module has no CSS
  to scan.
* No `setup:static-content:deploy` is required for the frontend on account of
  this module — `.phtml` files are not static content.

If you do want to change the markup, override
`Angeo_LlmsTxt::head/link_relations.phtml` in your theme as you would any
template. The block exposes `getMarkdownUrl()` and `getLlmsTxtUrl()`.

To switch the head tags off entirely while keeping the served files, set
*Output Formats → Emit Link Relations* to **No**.

---

## Extending — custom sanitizer filters

Insert your own filter between Page Builder and HTML stripping (e.g. to
remove `<script>` data attributes, redact phone numbers, etc.) by implementing
`Angeo\LlmsTxt\Api\SanitizerFilterInterface` and re-declaring the pipeline
in `di.xml`.

```xml
<type name="Angeo\LlmsTxt\Model\Sanitizer\Sanitizer">
    <arguments>
        <argument name="filters" xsi:type="array">
            <item name="cms_directive" xsi:type="object">Angeo\LlmsTxt\Model\Sanitizer\Filter\CmsDirectiveFilter</item>
            <item name="page_builder"  xsi:type="object">Angeo\LlmsTxt\Model\Sanitizer\Filter\PageBuilderFilter</item>
            <item name="redact_pii"    xsi:type="object">Vendor\Module\Sanitizer\Filter\PiiRedactionFilter</item>
            <item name="html"          xsi:type="object">Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter</item>
            <item name="whitespace"    xsi:type="object">Angeo\LlmsTxt\Model\Sanitizer\Filter\WhitespaceFilter</item>
        </argument>
    </arguments>
</type>
```

---

## Events

Hook in via observers — three events are dispatched per store/format pass:

| Event                              | Data                                              |
| :--------------------------------- | :------------------------------------------------ |
| `angeo_llms_generation_before`     | `store`, `format`, `context`                      |
| `angeo_llms_generation_after`      | `store`, `format`, `file`, `bytes`, `items`, `duration` |
| `angeo_llms_generation_failed`     | `store`, `format`, `error`                        |

The events are still dispatched per store **and per format**, even though
generation is now a single pass — the payload shape is unchanged from 3.x, so
existing observers keep working.

---

## Migrating from 2.x

* Old files in `media/llms/` can be deleted (output now lives in `media/angeo/llms/`).
* Any custom providers must be rewritten against `Api\EntityProviderInterface`; the 3.x `ProviderInterface` was removed in 4.0.0. See *Migrating from the 3.x `ProviderInterface`* above.
* Drop any reverse-proxy / Nginx rewrites pointing at the old paths.
* Re-run *Stores → Configuration → Angeo → LLMs.txt* to set the new fields (Page Builder strategy, customer group, etc.).
* External tooling that called the GET `/admin/angeo_llms/generate/index` URL must switch to the CLI command (the admin endpoint is now POST + CSRF).

---

## Beyond this module

The module gets your store's data *into* AI answer engines. Whether ChatGPT,
Claude, Gemini, or Perplexity actually *cite* you is a separate problem —
that's the part we do as a service:

* **Free AEO scan** — <https://angeo.dev/scan>: 9 externally-checked signals
  (structured data, entity clarity, llms.txt correctness, crawlability, and
  more) with a scored report for any Magento / Adobe Commerce store. Good
  first step right after installing this module.
* **AEO audit & implementation** — schema/entity optimization, citation
  building, and AI-visibility measurement across LLM providers, done for you.
  Case study: a demo store's AEO score going from 20% (Critical) to 86%
  (Excellent) — see <https://angeo.dev/>.
* **Agencies** — running this module on client stores? We partner with
  Magento agencies on white-label AEO audits. Write to us.

angeo.dev is a registered member of the Anthropic Claude Partner Network.

## License

MIT — see [LICENSE](./LICENSE).

## Support

* GitHub Issues: <https://github.com/angeo-dev/module-llms-txt/issues>
* Email: <support@angeo.dev>
* Free AEO scan: <https://angeo.dev/scan>
