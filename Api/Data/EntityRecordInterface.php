<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Api\Data;

/**
 * Format-agnostic entity record produced by an
 * {@see \Angeo\LlmsTxt\Api\EntityProviderInterface} in the single-pass
 * pipeline. One record per entity; renderers serialize it into each enabled
 * output format, so the catalog is read and sanitized exactly once.
 *
 * Textual content (getContent / getShortContent) is ALREADY sanitized at the
 * maximum length any format needs; renderers only truncate down.
 *
 * 4.0.0 added isInStock(), getImageUrl() and getAttributes(). Implementations
 * outside this module must add them; {@see \Angeo\LlmsTxt\Model\Data\EntityRecord}
 * takes them as optional trailing constructor arguments, so existing
 * instantiations keep compiling.
 *
 * @api
 * @since 3.2.0
 */
interface EntityRecordInterface
{
    public const TYPE_STORE    = 'store';
    public const TYPE_CATEGORY = 'category';
    public const TYPE_CMS_PAGE = 'cms_page';
    public const TYPE_PRODUCT  = 'product';

    public function getType(): string;

    public function getEntityId(): int;

    /** Display name (product/category name, CMS/page or store title). */
    public function getName(): string;

    /** Absolute frontend URL, or null when the entity has none. */
    public function getUrl(): ?string;

    /** Sanitized main content (description / page content), untruncated for formats. */
    public function getContent(): string;

    /** Sanitized short description (products only), '' otherwise. */
    public function getShortContent(): string;

    /** SKU (products only). */
    public function getSku(): ?string;

    /** Group-aware final price (products only). */
    public function getPrice(): ?float;

    /** CMS page identifier (cms_page only). */
    public function getIdentifier(): ?string;

    /** Resolved store summary line (store record only). */
    public function getSummary(): ?string;

    /**
     * Whether the product is salable in this store. Null when the record is
     * not a product, or when availability export is switched off.
     *
     * @since 4.0.0
     */
    public function isInStock(): ?bool;

    /**
     * Absolute URL of the product's base image, or null.
     *
     * @since 4.0.0
     */
    public function getImageUrl(): ?string;

    /**
     * Extra exported attributes, keyed by attribute code, values already
     * resolved to store-view labels. Empty when nothing is configured.
     *
     * @return array<string, string>
     * @since 4.0.0
     */
    public function getAttributes(): array;
}
