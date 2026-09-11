<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Data;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;

/**
 * Immutable {@see EntityRecordInterface} value object.
 *
 * @since 3.2.0
 */
final class EntityRecord implements EntityRecordInterface
{
    public function __construct(
        private readonly string $type,
        private readonly int $entityId,
        private readonly string $name,
        private readonly ?string $url = null,
        private readonly string $content = '',
        private readonly string $shortContent = '',
        private readonly ?string $sku = null,
        private readonly ?float $price = null,
        private readonly ?string $identifier = null,
        private readonly ?string $summary = null,
        private readonly ?bool $inStock = null,
        private readonly ?string $imageUrl = null,
        private readonly array $attributes = []
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getShortContent(): string
    {
        return $this->shortContent;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function isInStock(): ?bool
    {
        return $this->inStock;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /**
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
