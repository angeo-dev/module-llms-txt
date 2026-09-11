<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Shared, immutable-from-the-provider's-perspective context object passed to every
 * {@see ProviderInterface::provide()} call.
 *
 * Carries everything a provider may need to render output without having to resolve
 * it itself (the store, the customer group, the verbosity flag, the output format,
 * the URL transformer, etc.). This is the SPI counterpart to a Magento "scope" —
 * providers should treat the context as read-only and ask it for whatever they need.
 *
 * Custom providers contributed by third-party modules MUST consume this object via
 * the second argument of {@see ProviderInterface::provide()} rather than re-resolving
 * the store/locale/currency themselves; this guarantees they see the same scope as
 * the rest of the generation pipeline (frontend emulation, etc.).
 *
 * @api
 * @since 3.0.0
 */
interface OutputContextInterface
{
    /**
     * Format constants for {@see getFormat()}.
     */
    public const FORMAT_LLMS_TXT      = 'llms_txt';
    public const FORMAT_LLMS_FULL_TXT = 'llms_full_txt';
    public const FORMAT_JSONL         = 'jsonl';

    /** @since 3.4.0 — operator's manual for AI agents (agents.md convention). */
    public const FORMAT_AGENTS_MD     = 'agents_md';

    /**
     * Verbosity constants for {@see getVerbosity()}.
     *
     * COMPACT  — short summaries, products under ## Optional (default for llms.txt)
     * FULL     — full descriptions inline (for llms-full.txt)
     * DATASET  — every available field exposed (for JSONL)
     */
    public const VERBOSITY_COMPACT = 'compact';
    public const VERBOSITY_FULL    = 'full';
    public const VERBOSITY_DATASET = 'dataset';

    /**
     * Well-known shared-bag key (see {@see setShared()}): the type of entity whose
     * content is currently being sanitized — 'product', 'category', or 'cms-page'.
     *
     * Providers set this before calling the sanitizer so security-sensitive filters
     * (e.g. {@see \Angeo\LlmsTxt\Model\Sanitizer\Filter\CmsDirectiveFilter}) can apply
     * entity-specific policies, such as refusing to resolve {{block}}/{{widget}}
     * directives inside product attributes that may originate from imported feeds.
     *
     * @since 3.1.0
     */
    public const SHARED_ENTITY_TYPE = 'current_entity_type';

    /**
     * Get the store this generation is running for.
     */
    public function getStore(): StoreInterface;

    /**
     * Get the output format being generated.
     *
     * @return string  One of the FORMAT_* constants.
     */
    public function getFormat(): string;

    /**
     * Get the verbosity level for content sanitization and field selection.
     *
     * @return string  One of the VERBOSITY_* constants.
     */
    public function getVerbosity(): string;

    /**
     * Customer group ID whose pricing should be reflected in product output.
     *
     * Defaults to NOT_LOGGED_IN (group 0).
     */
    public function getCustomerGroupId(): int;

    /**
     * Resolved BCP-47 locale (e.g. "en-US", "de-DE") for the store.
     *
     * Already normalized — providers do NOT need to call str_replace on it.
     */
    public function getLocaleCode(): string;

    /**
     * Resolved display currency code for the store (e.g. "USD", "EUR").
     */
    public function getCurrencyCode(): string;

    /**
     * Absolute, trailing-slash-trimmed frontend base URL for the store.
     */
    public function getBaseUrl(): string;

    /**
     * Read-only bag of shared data published by earlier providers in the chain.
     *
     * Use case: {@see Angeo\LlmsTxt\Model\Provider\Llms\CategoryProvider} loads the
     * category tree and stores it here; later providers can read it without
     * re-querying. Returns null if the key was never set.
     *
     * @param string $key
     * @return mixed
     */
    public function getShared(string $key): mixed;

    /**
     * Publish a shared value for later providers in the same generation pass.
     *
     * Anything stored here is scoped to a single store's generation pass and
     * discarded immediately after — do NOT use this as a cache.
     */
    public function setShared(string $key, mixed $value): void;
}
