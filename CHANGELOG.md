# Changelog

All notable changes to this project will be documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.1.2] — 2025-04-29

### Added
- `Model/Config.php` — central config helper with `isEnabled()` and `isStoreExcluded()` methods
- Store-level **Exclude This Store** setting in admin (`Stores → Configuration → Angeo → LLMs.txt`)
- Global **Enabled** toggle now respected in CLI command, cron, generator, and controller
- Stale file cleanup — disabled or excluded stores have their generated files deleted on next generation run
- `Cron`: fixes admin URL being generated instead of frontend URL when cron runs
- `Controller`: immediate 404 if module is disabled, store is inactive, or store is excluded in config

### Fixed
- `getLocaleCode()` returning `null` — replaced with `ScopeConfigInterface::getValue('general/locale/code')` scoped to store
- Locale format normalized to BCP 47 (`en-US`) instead of Magento's internal `en_US` format
- `getBaseUrl()` returning admin URL in cron/CLI context — replaced with `getBaseUrl(URL_TYPE_WEB)`

---

## [2.0.0] — 2025-04-01

### Added
- `bin/magento angeo:llms:generate` CLI command with `--store`, `--no-jsonl`, `--no-llms` options
- `AbstractGenerator` — shared base class eliminating duplicate code between `LlmsGenerator` and `JsonlGenerator`
- Single `ProviderInterface` — replaces two identical interfaces from v1
- Per-store exception safety — one failing store no longer blocks others; errors are logged
- Files moved to `pub/media/angeo/llms/` and served via PHP controller (no direct web access to media dir)
- Admin config: product limit, JSONL toggle, per-store settings

### Fixed
- JSONL providers: `json_encode()` was called after `foreach` — only the last item was encoded, all others silently dropped
- `Cron` namespace triple-nested (`LlmsTxt\LlmsTxt\LlmsTxt\Cron`) — caused PHP fatal error on every cron run
- `$output` initialized before store loop — store N's file contained merged content from stores 1…N
- `Jsonl\CategoryProvider` missing root category filter — returned system categories (ID 1, 2) and categories from all store views

### Changed
- Output format now follows [llmstxt.org](https://llmstxt.org) spec: H1 title, `##` sections, markdown links
- Files relocated from `pub/media/` to `pub/media/angeo/llms/`

---

## [1.0.0] — 2025-01-01

### Added
- Initial release
- Basic `llms.txt` and JSONL generation for Magento 2 stores
- Cron-based daily generation
- Admin UI generation button
