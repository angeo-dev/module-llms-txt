# Changelog

All notable changes to **Angeo_LlmsTxt** are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [4.3.3] — 2026-09-16

Build and packaging release. Generated files do not change.

### Fixed

* **PHPStan stub file removed.** 4.3.2 shipped `stubs/magento-factories.php`,
  which declares Magento factory classes for static analysis only.
  `setup:di:compile` scans every `*.php` file in a module outside `Test/` and
  can `require_once` it, so those declarations could reach compilation. The
  file is gone: `bitexpert/phpstan-magento` generates the factories during
  analysis, as in the Mage-OS modules. The single-process PHPStan setting that
  came with the stubs is removed too.
* CI runs PHPStan once, in its own job on PHP 8.2. On PHP 8.3+ PHPStan's
  turbo extension breaks the factory generator in
  `bitexpert/phpstan-magento` 0.43; the upstream fix (0.44.0) is not tagged yet.
* **"Generate Now (Async)"** saves the cron schedule row through
  `Magento\Cron\Model\ResourceModel\Schedule` instead of the deprecated
  `AbstractModel::save()`. The PHPStan ignore rule for this is removed, so the
  code passes `bitexpert/phpstan-magento` without local exceptions.
* `etc/crontab.xml` points at the correct schema,
  `urn:magento:module:Magento_Cron:etc/crontab.xsd`.

### Changed

* `magento/module-cron` moved from `require-dev` to `require`, and
  `Magento_Cron` added to the module sequence. The module uses it at runtime
  (cron job, cron group, schedule controller).
* `Controller\Adminhtml\Generate\Schedule` takes an optional
  `ScheduleResource` argument with an object-manager fallback, so its
  constructor stays compatible with earlier 4.3.x releases.

### Build

- `.gitattributes` with `export-ignore`: the Composer package no longer
  contains `Test/`, `phpstan.neon` and `phpunit.xml`.

### Documentation

- README: one badge row across the suite — CI, Packagist version and
  downloads, PHP 8.1 – 8.5, supported Magento range, Mage-OS Extension
  Directory, license.

---

## [4.3.2] — 2026-09-12

Tooling release: GitHub Actions CI, PHPStan config with factory stubs,
PHPUnit 10.5 config, and small code fixes found by static analysis.

---

## [4.3.1]

* PHP 8.1 allowed again. Magento dependencies use caret ranges (`^103.0`
  etc.) instead of open `>=` ranges.

---

## [4.3.0] — 2026-09-08

Loose ends. Three defects that were known and carried, plus the integration
tests. No breaking changes, no new configuration.

### Fixed

* **Empty blockquote in llms.txt and llms-full.txt.** A store with no meta
  description and no Custom Summary produced a bare `> ` line. That is worse
  than having no summary: to a parser it looks like the spec's summary and
  carries nothing. The line is now omitted when the summary is empty, and
  `angeo:llms:validate` reports a bare `> ` as an error instead of counting it
  as a valid summary — which it had been doing since 4.0.0.
* **The `Sitemap:` line in agents.md never appeared.** It was hardcoded empty
  in 3.4.0 with a "resolved in a follow-up" comment. `SitemapUrlResolver` now
  reads the store's most recently generated sitemap and builds the URL. Reads
  the `sitemap` table directly rather than depending on Magento_Sitemap's
  classes: that module is removable, and a missing sitemap should omit a line,
  not fail generation.
* Removed `Model\Config\Source\PageBuilderContentType`, which nothing
  referenced — not `system.xml`, not `di.xml`, not any PHP file. It had been
  dead since the sanitizer was reworked.

### Added

* Integration tests for the router: that `/llms.txt` and
  `/sitemap_agentic_discovery.xml` are claimed rather than falling through to
  the CMS no-route handler, and that with the module disabled `/llms.txt`
  returns a plain 404 so a merchant can serve their own file from the web root.

  These need the Magento integration test framework and a test database. They
  are **not** in `phpunit.xml` and **not** in the GitHub Actions workflow,
  which runs without a Magento installation. See `Test/Integration/README.md`.

### Notes

* One planned item is deliberately not here. Referencing `llms.txt` and
  `agents.md` from `robots.txt` belongs in `angeo/module-robots-txt-aeo`, which
  owns that file and already does lossless RFC 9309 round-trip parsing. Writing
  robots.txt from two modules is how merchants end up with duplicated
  directives.

---

## [4.2.0] — 2026-09-07

Correctness release for one specific case: stores running more than one stock.
No breaking changes.

### Added

* **`Availability Source`** (`Product Data`, default **Auto**). Salable status
  is read through Multi-Source Inventory when MSI is installed, and through the
  legacy stock index otherwise. `Legacy` forces the old index.
* `Model\Stock\SalableStatusResolver`.

### Fixed

* **Availability could be wrong on multi-stock stores.** 4.0.0 started
  publishing salable status as a fact that an AI agent reads and repeats to a
  shopper, but it read that fact from `cataloginventory_stock_status`, which is
  not the source of truth for a sales channel once a store has more than one
  stock. Until 4.0.0 the same index was used only to *filter* out-of-stock
  products, where a wrong answer merely meant a product appeared or did not.
  Publishing "in stock" wrongly is worse.

  Single-source merchants — Default Source with Default Stock, which is most of
  them — are unaffected either way: MSI keeps the legacy index in sync for the
  default stock, so both paths give the same answer.

### Notes

* The MSI lookup is batched per collection page
  (`AreProductsSalableInterface::execute()`), never one call per product.
* If MSI is asked and cannot answer, the module logs it and falls back to the
  legacy index rather than publishing a guess. A SKU missing from a
  *successful* MSI batch is treated as not salable, which is what it means.
* MSI is optional. Adobe Commerce and Mage-OS both allow the `Magento_Inventory*`
  modules to be removed, so the dependency is resolved lazily inside
  `SalableStatusResolver` and nowhere else — type-hinting the interface in a
  constructor would break `setup:di:compile` on a store without MSI. Declared
  in composer `suggest`, not `require`.
* The out-of-stock *filter* still goes through the legacy stock helper, which
  MSI plugs into. Verify on a multi-stock store that filtering and export agree.

---

## [4.1.0] — 2026-09-07

Operational release. No breaking changes, no output-format changes — a 4.0.0
install can take this without touching configuration.

### Added

* **`Skip Generation When Nothing Changed`** (`Performance`, default **No**).
  When enabled, the nightly run skips a store whose products, categories, CMS
  pages and module configuration all pre-date its last successful generation —
  no catalog pass at all. The check is four `MAX()` reads on indexed timestamp
  columns.

  It is **off by default and that is deliberate.** Detection reads entity
  timestamps, so it does not see stock movements (`cataloginventory_stock_item`
  has no timestamp column) or prices changed by catalog price rules and
  scheduled updates. Since 4.0.0 exports availability, a store with moving
  stock would publish stale data. Turn it on only if your catalog changes by
  product saves. If detection fails for any reason, the store is regenerated.
* **`bin/magento angeo:llms:generate --force`** — rebuild regardless.
* **`Invalidate Markdown Mirror on Save`** (`Performance`, default **Yes**).
  Saving or deleting a product, category or CMS page now drops that entity's
  cached `.md` mirror, the way Magento invalidates the cached HTML page.
  Before this, an edited page kept serving its previous markdown until the
  HTTP TTL expired — an hour by default, longer if the merchant raised it.
  Only the affected entity's keys are removed, never the whole tag, so an
  import does not throw away the entire mirror cache.
* **`bin/magento angeo:llms:clean [--store=…] [--force]`** — deletes the
  generated files and flushes the mirror cache. For when a format is switched
  off and its stale file is still being served, and for removing the module's
  output before uninstalling. Asks for confirmation unless `--force`.
* `Model\Cache\MdMirrorCacheKey` — the mirror cache key rule, extracted so the
  controller that fills the cache and the observer that invalidates it cannot
  drift apart. Unit-tested.
* `Model\Pipeline\ChangeDetector`.

### Changed

* `SinglePassGenerator::generateAll()` and `GenerationService::generateAll()`
  take a third optional `bool $force` argument. Existing calls are unaffected.
* `Controller\Index\MdMirror` takes an optional trailing `MdMirrorCacheKey`.

### Not in this release, and why

The roadmap called this "incremental generation", implying only changed
products would be rewritten. That is not achievable for these formats and the
wording was wrong. `llms.txt`, `llms-full.txt` and `llms.jsonl` are each a
single ordered file per store, streamed and atomically renamed; rewriting one
product inside one means rewriting the file, which means reading the catalog
again. A per-entity delta would require splitting the output into fragments and
concatenating them, which changes the on-disk contract and the served bytes for
no gain a merchant can observe.

What actually costs time is running the pass at all on a store that did not
change — and that is what this release removes. On stores that do change every
night, the pass still runs in full, and the honest lever there remains
`Collection Page Size` and the cron schedule.

---

## [4.0.0] — 2026-09-07

Two things happen in this release. The deprecations announced in 3.2.0 are
carried out, and the module is brought in line with **llms.txt v2**
(llmstxt.org, 10 August 2026). **Read the upgrade notes before deploying.**

### Removed — BREAKING

* **The legacy generation pipeline.** Single pass is now the only pipeline.
  Deleted: `Api\ProviderInterface`, `Model\Provider\AbstractProvider`, the
  eight bundled providers under `Model\Provider\Llms\*` and
  `Model\Provider\Jsonl\*`, `Model\Generator\AbstractGenerator`,
  `LlmsTxtGenerator`, `LlmsFullTxtGenerator`, `JsonlGenerator`, and
  `Model\Config\Source\GenerationMode`.
* **`Performance → Generation Pipeline`** (`angeo_llms/performance/generation_mode`)
  and `Config::getGenerationMode()` / `isSinglePassEnabled()` /
  `MODE_LEGACY` / `MODE_SINGLE_PASS`. A data patch deletes the stored rows.
* **The compatibility pass for legacy providers.** A third-party module still
  registering a `ProviderInterface` implementation on one of the deleted
  generators will now fail at `setup:di:compile` with the missing class name.
  Migrate to `Api\EntityProviderInterface` — see the README.
* PHP 8.1 support. The floor is now 8.2, matching Magento 2.4.7+.

### Added — llms.txt v2

* **Link relations** (`Formats → Emit Link Relations`, default **Yes**).
  v2's headline addition: given a page, an agent should be able to find its
  markdown version and the llms.txt that covers it without guessing.
  - `<link rel="alternate" type="text/markdown">` and
    `<link rel="describedby">` in the `<head>` of product, category and CMS
    pages, via a template block you can override in a theme;
  - the same `rel="describedby"` as an HTTP `Link:` header on the served
    markdown mirrors, so the relation also survives for non-HTML resources.
  Requires markdown mirrors to be switched on.
* **Links inside llms.txt point at markdown mirrors**
  (`Formats → Link to Markdown Mirrors in llms.txt`, default **Yes** when
  mirrors are on). v2 asks that llms.txt links lead to LLM-friendly content.
  JSONL keeps the canonical `url` untouched and adds `md_url` beside it —
  replacing `url` would break every consumer already indexing the feed.
* **Both markdown URL forms are supported and documented.** v1 specified
  `page.html.md`; v2 also allows `page.md`. The mirror controller resolves
  either against `url_rewrite`, with or without the store's URL suffix.
* **`angeo:llms:validate` rewritten to the v2 rules**, with `--strict` for CI.
  It now checks that no heading sits between the summary and the first H2
  (v2: "sections of any type except headings"), that every section list item
  is a real markdown link, that links are absolute, and it warns when links
  point at HTML while mirrors are being served. Reads go through Magento's
  Filesystem abstraction, so it works on Adobe Commerce Cloud.

### Changed — BREAKING

* **`Products under "## Optional"` now defaults to No.** v2 removed the
  mechanical meaning of `## Optional`: it no longer instructs any tool to drop
  those links, it is only a convention for secondary content. Products are a
  store's primary content. Existing installs that saved an explicit value keep
  it; installs relying on the default will see products move from
  `## Optional → ### Products` to a top-level `## Products`.
* **`Api\Data\EntityRecordInterface` gained `isInStock()`, `getImageUrl()` and
  `getAttributes()`.** Implementations outside this module must add them.
  `Model\Data\EntityRecord` takes the three as optional trailing constructor
  arguments, so existing instantiations keep working.
* **JSONL schema is now 4.0.0** (`etc/jsonl-schema.json`) with `md_url`,
  `in_stock`, `image` and `attributes`. All four are omitted when not
  exported, so a consumer can tell "not exported" from "false" or "empty".

### Added — product data for agents

* **`Product Data` config group.** Availability (default Yes), base image URL
  (default Yes), a brand attribute code, and a free list of extra attribute
  codes. Availability and price are the two facts an AI shopping agent needs
  before it recommends anything; the module previously exported only price.
* Stock status is loaded in **batch per collection page**
  (`Stock::addStockStatusToProducts`), never one round-trip per product.
* Dropdown attributes are exported as their store-view labels, not option IDs.
  The configured brand attribute is re-keyed to `brand` so agents do not have
  to guess which attribute carries it. Empty values are dropped per product.

### Added — agents.md and discovery

* **`Agents.md Content` config group** — delivery, returns, privacy, terms,
  about and support links. Each takes an absolute URL or a path relative to
  the base URL, so a CMS identifier such as `shipping-policy` works as-is.
  They render as a `## Key pages` section; the section is omitted entirely
  when nothing is configured, so the file never carries an empty heading.
  Labels and values go through the same prompt-injection hygiene as the rest
  of agents.md.
* **`/sitemap_agentic_discovery.xml`** (`Formats → Serve Agentic Discovery
  Sitemap`, default **Yes**) — a small sitemap declaring only the agent-facing
  files, mirroring what Shopify publishes. Lists only formats that are
  actually enabled for the store: a sitemap pointing at a 404 is worse than
  no sitemap. Submit it in Search Console next to the main sitemap.

### Fixed

* Carried over from 3.4.1: the single-pass hub block read the output context
  from a stream-array key that was never written, which aborted generation for
  every store whose llms.txt had content. See the 3.4.1 entry.

### Compatibility

* **Hyvä: natively compatible, nothing to install.** The new head block emits
  two `<link>` tags and ships no JavaScript, CSS or LESS, so it renders
  identically under Luma and Hyvä. The layout files attach to
  `head.additional`, which Hyvä keeps as an extension point. No compatibility
  module, no `hyva_` layout variant, no `hyva-themes.json` entry, no Tailwind
  rebuild. Override `Angeo_LlmsTxt::head/link_relations.phtml` in a theme if
  you want different markup.
* The layout files use `referenceBlock` for `head.additional`. It is declared
  as a block in `Magento_Theme`, not a container (magento/magento2#16497);
  `referenceContainer` resolves by name at runtime but logs in developer mode.
* Magento 2.4.7+ / PHP 8.2–8.5.

### Added — tooling

* GitHub Actions CI: lint, `Magento2` coding standard, PHPStan and PHPUnit on
  PHP 8.2, 8.3, 8.4 and 8.5.
* Unit tests for `MarkdownUrl` and for the new agents.md sections.

### Upgrade notes

1. **Before upgrading**, if you run any third-party module that extends the
   generated files, check whether it implements `Angeo\LlmsTxt\Api\ProviderInterface`.
   If it does, it must be migrated to `Api\EntityProviderInterface` first —
   otherwise `setup:di:compile` will fail.
2. `composer require angeo/module-llms-txt:^4.0`
3. `bin/magento setup:upgrade && bin/magento setup:di:compile`
4. `bin/magento cache:flush`. No frontend `static-content:deploy` is needed on
   account of this release — the new template is a `.phtml`, which is not
   static content.
5. Review **Stores → Configuration → Angeo → LLMs.txt**: the new
   *Product Data* and *Agents.md Content* groups, and the three new fields
   under *Output Formats*.
6. `bin/magento angeo:llms:generate` then `bin/magento angeo:llms:validate --strict`.
7. Diff the regenerated files against your 3.4.x output. Expected differences:
   products no longer nested under `## Optional`, links pointing at `.md`
   mirrors when those are enabled, and availability on the product lines.

---

## [3.4.1] — 2026-09-07

Hotfix. **Upgrade immediately if you run the single-pass pipeline.**

### Fixed

* **Single-pass generation aborted for every store whose llms.txt had
  content.** `SinglePassGenerator` read the output context from a key that
  was never written to the per-format stream array (`$s['context']`), so
  rendering the 3.4.0 "For Agents & Developers" hub block raised an `Error`.
  The error was swallowed by the pass-level `catch (\Throwable)`, which then
  discarded **all** temporary streams for that store. Net effect with
  `Performance → Generation Pipeline = Single pass`: llms.txt, llms-full.txt
  and llms.jsonl were never written, the previous files kept being served
  until they went stale, and the admin status panel showed a failure whose
  message did not point at the cause.

  Stores running the default `legacy` pipeline were **not** affected —
  the legacy generators resolve the context separately.

### Notes

* No configuration, API, file-path or output-format changes. Drop-in upgrade
  from 3.4.0: `composer require angeo/module-llms-txt:3.4.1`, then
  `bin/magento setup:upgrade && bin/magento cache:flush` and re-run
  `bin/magento angeo:llms:generate`.
* 4.0.0 makes single-pass the only pipeline, so this fix is a prerequisite
  for that release.

---

## [3.4.0] — 2026-07-04

agents.md release — storefront parity with the convention Shopify rolled out
to all stores in May 2026. Fully additive, **no breaking changes**.

### Added

* **agents.md** (`/agents.md`, config: `Formats → Generate agents.md`,
  default **Yes**) — an operator's manual addressed to AI agents: store
  facts, machine-readable surfaces, catalog search template, and rules of
  interaction (robots.txt authority, live-data preference, checkout on the
  merchant's site). Generated by a standalone `AgentsMdGenerator` invoked by
  `GenerationService`, so it works identically under the legacy and
  single-pass pipelines; atomic tmp+rename publish; status recorded in the
  same repository the admin panel reads; stale file deleted when disabled.
* **Soft sibling-module detection** (`SurfaceRegistry`) — when
  `Angeo_Ucp` / `Angeo_McpServer` are installed, agents.md and the llms.txt
  hub block link the `/.well-known/ucp` profile and the `/mcp` endpoint; no
  hard Composer dependency.
* **"For Agents & Developers" hub block in llms.txt** — emitted by both
  pipelines right before the attribution signature (llms.txt format only),
  mirroring Shopify's native llms.txt structure. Empty when no surfaces
  exist — no orphan headings.
* **Prompt-injection hygiene** in agents.md: owner-editable fields (store
  name, summary) are stripped of markdown-structural characters before
  rendering — agents are designed to trust this file, so it must not be a
  vector.
* Third signature variant (`utm_medium=agents-md`) so the new surface is
  measurable independently.
* Unit tests: `AgentsMdBuilderTest` (structure, conditional surfaces,
  injection hygiene, hub block), extended `SignatureTest`.

### Notes for extension developers

* `AbstractGenerator`, `SinglePassGenerator`, and `GenerationService` gained
  optional, defaulted constructor parameters only — 3.0–3.3 signatures keep
  working.
* New format constant `OutputContextInterface::FORMAT_AGENTS_MD`;
  `FilePathResolver` maps it to `media/angeo/llms/agents_{storeCode}.md`.
  The single-pass entity pipeline intentionally skips this format (it is
  store-level metadata, not catalog content).

---

## [3.3.0] — 2026-07-02

Attribution & discoverability release. No behavioral changes to generation
logic, file paths, events, or extension points. **Fully backward compatible.**

### Added

* **Attribution signature** (`Model/Output/Signature`, config:
  `General → Attribution Signature`, default **Yes**). Appends a one-line
  spec-compliant markdown footer — `Generated by Angeo LlmsTxt vX.Y.Z …` with
  a link to the free AEO scan — to `llms.txt`, `llms-full.txt`, and the
  on-the-fly `.md` mirrors. Details:
  - **never** emitted into JSONL (one-JSON-record-per-line is a hard format
    contract);
  - emitted by **both** pipelines (legacy and single-pass) byte-identically,
    always as the last content in the file (after third-party legacy
    providers in single-pass mode);
  - in `.md` mirrors it is appended **before** caching, so cached and fresh
    responses are byte-identical and `Content-Length` stays correct;
  - only written when the file has real content (empty outputs still produce
    no file at all);
  - not counted as an "item" in generation stats;
  - can be disabled per store/website/default scope with no loss of features.
* **Status panel next-step CTA** — the admin status panel now shows a
  one-line pointer to the free AEO scan and support contact under the table.
* **CLI next-step tip** — `angeo:llms:generate` prints a one-line pointer to
  the free AEO scan after at least one successful store generation.
* Unit tests for the signature contract
  (`Test/Unit/Model/Output/SignatureTest`).

### Fixed

* `angeo:llms:generate` printed a hardcoded, outdated banner
  (`Angeo LLMs.txt Generator 3.0`). The banner now reads the new
  `Config::MODULE_VERSION` constant, which is kept in sync with
  `composer.json`.

### Notes for extension developers

* `AbstractGenerator`, `SinglePassGenerator`, and the `MdMirror` controller
  each gained one **optional, defaulted** constructor parameter
  (`?Signature $signature = null`). Existing subclasses and DI configurations
  compiled against the 3.0–3.2 signatures keep working unchanged.

---

## [3.2.0] — 2026-06-10

Single-pass generation pipeline (opt-in). **Fully backward compatible**: the
default mode remains `legacy`, all pre-3.2 behavior, file paths, events, and
extension points keep working unchanged. Everything superseded is marked
`@deprecated` and will be removed in **4.0.0**.

### Added

* **Single-pass pipeline** (`Model/Pipeline/SinglePassGenerator`). With
  `Stores → Configuration → Angeo LLMs.txt → Performance → Generation
  Pipeline = Single pass`, each store's catalog is iterated **once** and every
  enabled format (llms.txt, llms-full.txt, llms.jsonl) is rendered from that
  one pass:
  - one frontend emulation per store (legacy: one per format),
  - one url_rewrite warm-up per store (legacy: one per format),
  - each entity loaded and **sanitized exactly once** (legacy: 2–3× per
    product description),
  - all format files written in parallel streams with atomic rename, under one
    per-store lock (`media/angeo/llms/store_{code}.lock`).
  Combined with 3.1.1 this gives roughly 3× faster generation on top of the
  3.1.1 gains, with identical output files.
* **New `@api` extension points** (implement these going forward):
  - `Api\EntityProviderInterface` — yields format-agnostic entity records once
    per entity (successor of the format-specific `ProviderInterface`);
  - `Api\Data\EntityRecordInterface` + `Model\Data\EntityRecord` — immutable
    record DTO carrying already-sanitized content;
  - `Api\FormatRendererInterface` — serializes records into one output format;
  - `Model\Output\FilePathResolver` — the single source of truth for generated
    file paths (used by both pipelines and the frontend controller);
  - `Model\Text\Truncator` — shared word-boundary truncation (the Sanitizer
    now delegates to it; behavior is byte-identical).
* Bundled single-pass providers/renderers registered via `di.xml`
  (`SinglePassGenerator` → `entityProviders`, `renderers`). Third parties add
  their own items the same way.
* `Model/Config/Source/GenerationMode` + new system.xml field
  `angeo_llms/performance/generation_mode` (global scope, default `legacy`).
* Unit tests: `TruncatorTest`, including the down-truncation invariant that
  guarantees single-pass renderers reproduce legacy truncation byte-for-byte.

### Backward compatibility

* `generation_mode` defaults to **legacy** — upgrading changes nothing until
  you opt in.
* In single-pass mode the output files, on-disk paths, served URLs, generation
  status records, and the `angeo_llms_generation_before/after/failed` events
  (dispatched per format) are identical to legacy.
* **Custom providers built on the legacy `ProviderInterface` keep working in
  both modes.** In single-pass mode they are detected automatically (anything
  registered on the legacy generators beyond the bundled providers) and
  executed through a compatibility pass that appends their output to the
  corresponding format stream.
* The only semantic difference: the `items` counter in generation status now
  counts rendered records rather than raw stream chunks.

### Deprecated (removal in 4.0.0)

* `Api\ProviderInterface` and `Model\Provider\AbstractProvider` — implement
  `Api\EntityProviderInterface` instead.
* All eight bundled legacy providers under `Model\Provider\Llms\*` and
  `Model\Provider\Jsonl\*` — superseded by `Model\Pipeline\Provider\*` +
  format renderers.
* `Model\Generator\AbstractGenerator`, `LlmsTxtGenerator`,
  `LlmsFullTxtGenerator`, `JsonlGenerator` — superseded by
  `SinglePassGenerator`; file-path resolution moved to `FilePathResolver`.
* The `legacy` generation mode itself: 4.0.0 ships single-pass as the only
  pipeline and removes everything listed above.

### Changed (internal, not `@api`)

* `Service\GenerationService` routes by generation mode; new constructor
  dependency (`SinglePassGenerator`).
* `Controller\Index\Index` resolves file paths via `FilePathResolver` instead
  of the deprecated generators (constructor change).
* `Model\Sanitizer\Sanitizer` accepts an optional `Truncator` (defaults
  internally — existing instantiations and tests are unaffected).
* `AbstractGenerator::getProviders()` added so the single-pass pipeline can
  discover third-party legacy providers.

### Upgrade notes

1. `bin/magento setup:upgrade && bin/magento setup:di:compile`
2. Optional but recommended: switch *Performance → Generation Pipeline* to
   **Single pass**, run `bin/magento angeo:llms:generate`, and diff the
   generated files against the legacy output for your data.
3. If you maintain custom providers, plan their migration to
   `EntityProviderInterface` before 4.0.0.

---

## [3.1.1] — 2026-06-10

Performance release. No public-API changes; drop-in upgrade from 3.1.0.

### Performance

* **Out-of-stock filtering moved into SQL.** Both `ProductProvider`s now use
  `StockHelper::addIsInStockFilterToCollection()` (a JOIN on
  `cataloginventory_stock_status`) instead of one `StockRegistry` round-trip
  per product. On a 100k-SKU catalog with *Exclude Out-of-Stock* enabled this
  removes ~100,000 queries per format per store.
* **Prices come from the price index.** Product collections call
  `addPriceData($customerGroupId, $websiteId)`; the final price (group-aware,
  special-/tier-price-aware) is read from the joined
  `catalog_product_index_price` column instead of invoking the PHP price
  calculation chain per product — which for configurable/bundle products
  lazy-loads child products (another hidden N+1). A per-product fallback to the
  legacy calculation remains for rows missing from the index (e.g. reindex
  pending).
* **Dedicated cron group `angeo_llms`** with `use_separate_process=1`
  (new `etc/cron_groups.xml`). Long generation runs no longer block
  default-group jobs (transactional emails, scheduled indexers, etc.).
* **Default `collection_page_size` lowered 1000 → 500.** Each page holds full
  HTML descriptions of every product in memory; 500 halves the peak without a
  measurable throughput cost. Explicitly configured values are unaffected.
* **Duplicate-description sanitization skipped** in `llms-full.txt`: when
  `description` is byte-identical to `short_description` (a common merchant
  pattern), the content is sanitized once instead of twice.

### Behavior notes

* *Exclude Out-of-Stock* is now strict: products whose stock status cannot be
  resolved are excluded by the SQL filter, whereas 3.1.0 included them on
  lookup failure ("default in stock"). With a healthy stock index the output
  is identical.
* Prices require the **price index to be up to date** (`bin/magento indexer:reindex
  catalog_product_price`) — standard for any production store; stale index
  rows fall back to the slow per-product calculation rather than emitting a
  wrong price.
* The cron job moved from group `default` to group `angeo_llms`. If your
  crontab invokes `bin/magento cron:run` with explicit `--group` filters, add
  the new group.
* Internal constructor change (not `@api`): both `ProductProvider`s now take
  `Magento\CatalogInventory\Helper\Stock` instead of
  `StockRegistryInterface`. Recompile DI (`setup:di:compile`); if you extended
  these concrete classes, update your constructors.

### MSI note

Stock filtering still reads the legacy `cataloginventory_stock_status` table,
which MSI keeps in sync for the default stock. Multi-source/multi-stock setups
that need salable-quantity semantics per stock should override the providers —
now a single JOIN swap instead of a per-product call.

---

## [3.1.0] — 2026-06-10

Security & hardening release following an external security code review.
Upgrading is **strongly recommended** for all installations, especially those
with the `.md` mirror feature enabled.

### Security

* **[HIGH] `.md` mirror no longer serves disabled or hidden entities**
  (information disclosure). `Controller/Index/MdMirror` now verifies entity
  state before rendering: products must be *Enabled*, catalog-visible, and
  assigned to the current website; categories must be active; CMS pages must
  be active. Previously a stale `url_rewrite` row could expose embargoed,
  recalled, or intentionally unpublished content — including price and full
  description — at `/{url_key}.md`. Hidden entities now return the same 404
  as unknown paths, so their existence is not confirmed.
* **[HIGH] `.md` mirror DoS mitigation.** Rendered markdown is now cached in
  the Magento cache (tag `ANGEO_LLMS_MD`, TTL = configured HTTP Cache-Control
  TTL), so crawls no longer re-trigger entity loads, CMS directive resolution,
  and DOM-based sanitization on every request. Unknown paths are
  negative-cached for 5 minutes to blunt enumeration sweeps; request paths
  longer than 1024 bytes are rejected outright. The cache is flushed
  automatically after every generation run, so mirrors never serve a stale
  catalog state for a full TTL.
* **[HIGH] Frontend router no longer hijacks the `*.md` URL space**
  (route hijacking / availability). The router `sortOrder` moved from 10 to
  70 — after the urlrewrite (20), standard (30), and CMS (60) routers — so any
  real merchant content whose URL ends in `.md` always wins; this module only
  claims paths that would otherwise 404. The `.md` branch is additionally
  gated on the md-mirror feature being enabled for the resolved store: when
  the feature is off, the router declines the match instead of swallowing the
  request with a 404.
* **[MEDIUM] Template-directive injection surface reduced for product content.**
  `{{block}}` / `{{widget}}` / `{{var}}` resolution inside *product* attribute
  content (descriptions frequently imported from supplier/PIM feeds) is now
  controlled by a separate flag, `angeo_llms/sanitizer/resolve_directives_products`,
  **default OFF**. When off, directives found in product content are stripped —
  never resolved and never leaked as source. CMS pages and categories keep the
  existing `resolve_directives` behavior. On any directive-resolution failure
  the filter now strips directive source instead of returning it raw.
* **[MEDIUM] `HtmlFilter` output-encoding fixes** (stored-XSS defense for
  downstream consumers; secret-leak prevention):
  * HTML entities are decoded *before* the final tag-strip pass, then the
    result is stripped again — `&lt;script&gt;…&lt;/script&gt;` can no longer
    materialize as live markup in the generated output.
  * Unterminated `<script>` / `<style>` blocks (and unterminated HTML
    comments) are removed to end-of-input, so inline JS — which can carry
    analytics tokens or API keys — can never leak into `llms.txt`,
    `llms-full.txt`, or `.md` mirrors.
* **[MEDIUM] Wholesale-price disclosure warning.** The *Customer Group for
  Pricing* admin field now carries an explicit warning that the generated
  files are public and CDN-cacheable, and that selecting a logged-in / B2B
  group publishes that group's negotiated pricing to the internet.
* **[LOW] Admin error messages no longer expose exception internals.** The
  "Generate Now" and "Schedule" actions log full exceptions to
  `var/log/system.log` and show a generic message in the admin UI.
* **[LOW] `X-Content-Type-Options: nosniff`** is now sent on all `.md` mirror
  responses and all 404 responses (previously only on the file endpoint's
  200 responses).
* **[LOW] Admin status panel** embeds its polling URL via `json_encode()`
  instead of raw string interpolation inside a `<script>` block, per Magento
  secure-rendering guidelines.

### Fixed

* **Large-file serving no longer loads the whole file into PHP memory.**
  `Controller/Index/Index` streams files above 4 MB to the client in 256 KB
  chunks; concurrent requests for a multi-hundred-MB `llms-full.txt` can no
  longer exhaust the PHP memory limit. `Content-Length` is now always sent.
* **All file serving goes through Magento's `Filesystem` abstraction** —
  no native `is_file` / `filemtime` / `file_get_contents` on raw paths —
  making the endpoint compatible with Adobe Commerce Cloud remote storage
  (AWS S3) drivers.
* **Generation status writes are now concurrency-safe.**
  `GenerationStatusRepository` performs a locked read-modify-write (flock on a
  sidecar lock file) followed by an atomic tmp-rename, so parallel
  generators / cron / CLI runs can no longer lose each other's updates or
  leave a truncated `status.json`.
* **"Schedule (Async)" no longer piles up duplicate cron jobs.** A new run is
  only queued when no `angeo_llms_generate` row is already pending or running;
  the admin is informed otherwise.
* Corrected a misleading comment in `MdMirror`: the rewrite-lookup fallback
  appends the configured `.html` URL suffix (it never tried a trailing slash).

### Changed

* `UrlResolver::warmUp()` streams `url_rewrite` rows from the DB cursor
  instead of `fetchAll()`, roughly halving peak memory on very large rewrite
  tables.
* New public API: `AbstractGenerator::getRelativePath()` (media-relative path
  of the generated file; preferred over `getFilePath()` for
  Filesystem-abstraction readers). `getFilePath()` is retained for backward
  compatibility.
* New well-known shared-context key
  `OutputContextInterface::SHARED_ENTITY_TYPE`; all bundled providers and the
  `.md` mirror publish it before sanitizing so filters can apply
  entity-specific policies. Third-party providers are encouraged to do the
  same.
* Admin field comments updated (md-mirror caching behavior, directive
  resolution semantics).

### Added

* Config: `angeo_llms/sanitizer/resolve_directives_products` (default `0`).
* Cache tag `ANGEO_LLMS_MD` for rendered `.md` mirrors (flush with
  `bin/magento cache:clean` or automatically on each generation run).
* Unit tests: `HtmlFilter` security regressions (unterminated script blocks,
  entity-encoded markup resurrection, legitimate `<` text preservation) and
  `CmsDirectiveFilter` product-content gating.

### Upgrade notes

* Run `bin/magento setup:upgrade && bin/magento cache:flush` after deploying.
* If you relied on `{{widget}}` / `{{block}}` directives inside **product
  descriptions** being rendered into the generated files, re-enable this
  explicitly at *Stores → Configuration → Angeo → LLMs.txt → Content
  Sanitization → Resolve Directives in Product Content* after reviewing the
  security note on that field.
* If a customization called `AbstractGenerator::getFilePath()` to read
  generated files, consider migrating to `getRelativePath()` plus a
  `Filesystem` media read-directory for remote-storage compatibility.
* Behavior change: URLs ending in `.md` that collide with real merchant
  content are now served by that content (the mirror no longer takes
  precedence). URLs of hidden or disabled entities now return 404.

---

## [3.0.5] — 2026-06-04

Admin-config bugfix. Safe drop-in upgrade from 3.0.x.

### Fixed

* **System Config "Save Config" no longer throws `Cannot read properties of
  undefined (reading 'settings')`.** The `Generate` button `frontend_model`
  template (`generate_button.phtml`) rendered two `<form>` elements *inside*
  the admin system-config form (`#config-edit-form`). Nested forms are invalid
  HTML: the browser re-parents the inner inputs/buttons onto the outer form, so
  on Save the jQuery validator (`jquery.validate.js metadataRules`) iterated an
  orphaned submit button that has no rule metadata and crashed, aborting the
  whole submit. The buttons are now plain `type="button"` elements that POST via
  a JS-built form appended to `<body>` (outside the config form). CSRF
  protection is unchanged — the form key is still submitted.

---

Install-blocking bugfix plus PHP 8.5 support. Safe drop-in upgrade from 3.0.x.

### Fixed

* **`setup:upgrade` no longer fails XSD validation** on `etc/adminhtml/system.xml`.
  Two `<comment>` elements (`cache_ttl_seconds` and `schedule`) contained raw
  `<code>` HTML without a CDATA wrapper. `system_file.xsd` only allows a `model`
  child inside `<comment>`, so the literal markup tripped
  `Element 'code': This element is not expected. Expected is ( model )` and
  aborted module loading. Both comments are now wrapped in `<![CDATA[ … ]]>`,
  matching every other HTML-bearing comment in the file.

### Changed

* **Added PHP 8.5 to the supported range** (`…||~8.5.0`). Intended for Magento
  2.4.9+, which is the first line to support PHP 8.5; on 2.4.8 and earlier,
  PHP 8.4 remains the recommended runtime.

---

Admin-config bugfix. No functional or API changes — safe drop-in upgrade
from 3.0.x.

### Fixed

* **System Config "Save Config" no longer throws a JS `TypeError`.** Three
  numeric fields in `etc/adminhtml/system.xml` declared validation classes
  that are not registered in Magento's `mage/validation` ruleset
  (`validate-greater-than-zero` and `integer`). On 2.4.8-p4 the admin form
  validator (`jquery.validate.js` `metadataRules`) looks up
  `settings` on each rule object; the missing rules resolved to `undefined`,
  producing `Cannot read properties of undefined (reading 'settings')` and
  aborting the entire form submit. Replaced with registered rules:
  * `collection_page_size`: → `validate-digits validate-digits-range digits-range-0-1000000`
  * `product_limit`: → `validate-digits`
  * `cache_ttl_seconds`: → `validate-digits`

---

## [3.0.4] — 2026-06-03

Compatibility patch. No functional or API changes — safe drop-in upgrade
from 3.0.x.

### Changed

* **Lowered the minimum PHP to 8.1** (`~8.1.0||~8.2.0||~8.3.0||~8.4.0`).
  The module uses no PHP 8.2+ only syntax, so it runs on 2.4.5 / 2.4.6 stores
  that are still on PHP 8.1 as well as on 2.4.7 / 2.4.8 (PHP 8.3 / 8.4).
* **Broadened dependency constraints to cover 2.4.5 through 2.4.8.** Every
  Magento dependency in `require` now uses an open lower-bound (`>=`) pinned to
  the major line that shipped with 2.4.5 — e.g. `magento/framework: >=102.0`
  and `magento/module-url-rewrite: >=102.0`. Because these major lines do not
  change between 2.4.5 and 2.4.8, the module installs cleanly across all of
  those minors. This replaces the earlier exact carets (such as the `^101.2`
  on `module-url-rewrite`) that failed on 2.4.8, where that module ships as
  102.x.

---

## [3.0.2] — 2026-06-03

Marketplace-readiness patch. No functional or API changes — safe drop-in
upgrade from 3.0.0.

### Fixed

* **Replaced `md5()` with `hash('sha256', …)`** for ETag generation in the
  file-serving controller. The Magento Coding Standard forbids `md5()`; the
  ETag only needs to be stable and unique, so the switch is behaviour-neutral.
* **Removed error-silencing `@` operators** from filesystem calls
  (`fopen` / `flock` / `fclose`) in the atomic-write lock helper and in the
  validate command. Return values were already checked explicitly, so
  dropping `@` changes no behaviour while clearing the coding-standard errors.

### Changed

* **Dependency constraints pinned to real 2.4.x major lines.** `require` now
  uses caret ranges matching the actual published modules — notably
  `magento/module-url-rewrite: ^102.0` (the 101.2 line never existed). This
  resolves a `composer require` failure on clean 2.4.8 installs.
* Added an explicit `version` field (`3.0.1`) to `composer.json` so the
  package version matches the Marketplace submission form.

---

## [3.0.0] — 2026-05-23

A full rebuild against the architectural review of 2.1.4. This release is
**not drop-in compatible** — see the *Breaking Changes* section below for
migration steps.

### Breaking changes

* **`ProviderInterface::provide()` signature changed** from `string` to
  `iterable<string>`. Custom providers contributed by third-party modules
  must now yield chunks rather than return one concatenated string. This is
  the change that lets the generator stream to disk with bounded memory.
* **`/llms-full.txt` now serves a genuinely-different file** (full sanitized
  descriptions inline). Previously, this URL silently aliased to `/llms.txt`,
  which was misleading.
* **llms.txt header is now spec-compliant.** A single blockquote summary line,
  with currency / locale / base-URL moved to a plain markdown paragraph below.
  The 2.x output used four blockquote lines, which broke llmstxt.org-spec
  parsers.
* **Status tracking moved** out of `core_config_data` and into
  `var/angeo_llms/status.json`. Old status rows under `angeo_llms/status/*`
  are no longer read. Drop them via `bin/magento config:set --lock-env angeo_llms/status/... ""` if you want a clean state, but it's harmless to leave them.
* **`media/llms/` is no longer used** as the file output directory; output now
  lives under `media/angeo/llms/`. Old files can be deleted; remove any reverse-proxy rewrites pointing at the old path.
* **Admin "Generate" action moved to POST + CSRF**. If you have any external
  tooling that hit the old GET URL, switch to the CLI command instead.
* **Module namespace unchanged**: still `Angeo\LlmsTxt`. Composer package
  name unchanged.

### Added

* **Page Builder element filter** with four strategies — *preserve*, *exclude*,
  *allow*, *strip* — driven by the element's `data-content-type` attribute.
  Default list of excluded types drops common visual-only elements
  (products carousel, banner, slider, video, map, buttons, block,
  dynamic-block, divider, spacer) so the output focuses on semantic text.
  Configurable per-store at *Stores → Configuration → Angeo → LLMs.txt →
  Content Sanitization*.
* **Streaming generation** via PHP generators. Memory stays bounded at one
  collection page (default 1000 products) regardless of catalog size.
* **Atomic writes**: each file is written to `.tmp`, then renamed. Readers
  never see a half-written file. Generation locks via a separate `.lock` file
  with `flock(LOCK_EX | LOCK_NB)`, so concurrent runs cannot corrupt output.
* **Cursor pagination** by `entity_id ASC > $lastId` instead of skip/limit, so
  products inserted mid-run can neither be duplicated nor skipped.
* **Batch URL resolver** loads every URL rewrite for a store in one query
  (vs. the per-product `getProductUrl()` query that 2.x triggered N times).
* **Real `llms-full.txt`** with full sanitized descriptions inline.
* **`/{url_key}.md` mirrors** — every product, category, and CMS page exposes
  a clean Markdown rendering at its URL with `.md` appended. Generated on the
  fly; no extra disk storage.
* **CMS directive resolution** — `{{widget}}`, `{{block}}`, `{{var}}`, and
  `{{store}}` directives are now rendered via Magento's standard frontend
  filter before being stripped, instead of leaking as literal text.
* **Customer-group-aware pricing** — admin can choose which customer group's
  final price (with special-price and group-price applied) gets exposed.
* **HTTP caching** — `ETag`, `Last-Modified`, `Cache-Control: public, max-age=`,
  `X-Robots-Tag: noindex, follow`, and 304 responses on conditional GETs.
* **Async admin action** — *Schedule (Async)* inserts a `cron_schedule` row for
  the next tick so admins don't have to wait through a synchronous generation.
* **Live admin status panel** polling `/angeo_llms/status/index` every 60s.
* **Three CLI commands**:
  * `bin/magento angeo:llms:generate [--store=…] [--no-jsonl] [--no-llms] [--no-full]`
  * `bin/magento angeo:llms:status`
  * `bin/magento angeo:llms:validate [--store=…]`
* **JSONL JSON-Schema** at `etc/jsonl-schema.json` for downstream pipelines.
* **Events**: `angeo_llms_generation_before`, `angeo_llms_generation_after`,
  `angeo_llms_generation_failed` — for custom hooks.
* **PHPUnit test suite** under `Test/Unit/`.

### Changed

* `frontend_default_meta_description` is now the fallback for the store
  summary, before falling back to the generic stub.
* Multi-store store-code routing handles the last URL path segment, so
  `/de/llms.txt` works on path-based stores.
* Spec compliance: products go under `## Optional` by default (admin
  toggleable) so context-budget-constrained clients can drop them.
* Out-of-stock products excluded by an explicit `StockRegistry` lookup
  (configurable).
* Logger context is now structured: every log line is prefixed
  `[Angeo LlmsTxt]` and includes store/format keys.

### Fixed

* **Pseudo-locking** in 2.x: a `'w'` open truncates the file before the
  `flock()` call, so two concurrent generations both saw an empty file and
  the last writer won unpredictably. 3.0 uses a separate `.lock` file.
* **CSRF-exposed admin generate**: 2.x used a GET URL; 3.0 requires POST with
  the form key.
* **Synchronous admin "Generate" timing out** on large catalogs (now async option).
* **N+1 URL rewrite queries**: now batched.
* **Literal `{{widget}}` text** appearing in 2.x output: now resolved.
* **Stale files** for stores that became inactive or excluded: now cleaned up
  on every generation run.

### Removed

* `media/llms/` legacy directory (see breaking-changes notes).
* GET endpoint for admin generation.
* Documented-but-non-existent config fields from 2.x README.

---

## [2.1.4] — Pre-rebuild baseline

Last release in the 2.x line. See the architectural review document for
the issues that motivated 3.0.0.