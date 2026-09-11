<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Renderer;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;

/**
 * JSONL renderer — one JSON object per line, conforming to
 * etc/jsonl-schema.json.
 *
 * The signature is never written here: one record per line is a hard format
 * contract and a markdown footer would corrupt it.
 *
 * @since 3.2.0
 */
class JsonlRenderer extends AbstractRenderer
{
    private const PRODUCT_SHORT_MAX = 2000;
    private const EMBED_MAX         = 8000;

    public function render(EntityRecordInterface $record, OutputContextInterface $context): iterable
    {
        $store = $context->getStore();

        switch ($record->getType()) {
            case EntityRecordInterface::TYPE_STORE:
                yield $this->encodeJsonl([
                    'entity_type'    => 'store',
                    'entity_id'      => $record->getEntityId(),
                    'store_code'     => $store->getCode(),
                    'store_name'     => (string) $store->getName(),
                    'url'            => $context->getBaseUrl(),
                    'currency'       => $context->getCurrencyCode(),
                    'locale'         => $context->getLocaleCode(),
                    'embedding_text' => trim($store->getName() . ' ' . $context->getBaseUrl()),
                ]);
                return;

            case EntityRecordInterface::TYPE_CATEGORY:
                $description = $record->getContent();
                yield $this->encodeJsonl([
                    'entity_type'    => 'category',
                    'entity_id'      => $record->getEntityId(),
                    'store_code'     => $store->getCode(),
                    'store_name'     => (string) $store->getName(),
                    'name'           => $record->getName(),
                    'url'            => $record->getUrl(),
                    'md_url'         => $this->mdUrl($record->getUrl(), $context),
                    'description'    => $description,
                    'embedding_text' => mb_substr(
                        trim($record->getName() . "\n" . $description),
                        0,
                        self::EMBED_MAX
                    ),
                ]);
                return;

            case EntityRecordInterface::TYPE_CMS_PAGE:
                $content = $record->getContent();
                yield $this->encodeJsonl([
                    'entity_type'    => 'cms_page',
                    'entity_id'      => $record->getEntityId(),
                    'store_code'     => $store->getCode(),
                    'store_name'     => (string) $store->getName(),
                    'title'          => $record->getName(),
                    'identifier'     => (string) $record->getIdentifier(),
                    'url'            => $record->getUrl(),
                    'md_url'         => $this->mdUrl($record->getUrl(), $context),
                    'content'        => $content,
                    'embedding_text' => mb_substr(
                        trim($record->getName() . "\n" . $content),
                        0,
                        self::EMBED_MAX
                    ),
                ]);
                return;

            case EntityRecordInterface::TYPE_PRODUCT:
                // Legacy jsonl truncated short_description at 2000; record carries
                // up to 5000 (full-txt max) — truncate down identically.
                $short = $this->truncator->truncate($record->getShortContent(), self::PRODUCT_SHORT_MAX);
                $desc  = $record->getContent();
                $row = [
                    'entity_type'       => 'product',
                    'entity_id'         => $record->getEntityId(),
                    'store_code'        => $store->getCode(),
                    'store_name'        => (string) $store->getName(),
                    'sku'               => (string) $record->getSku(),
                    'name'              => $record->getName(),
                    'url'               => $record->getUrl(),
                    'md_url'            => $this->mdUrl($record->getUrl(), $context),
                    'price'             => (float) $record->getPrice(),
                    'currency'          => $context->getCurrencyCode(),
                    'short_description' => $short,
                    'description'       => $desc,
                ];

                // 4.0.0: the two facts an agent needs before recommending, plus
                // whatever the merchant configured. Omitted entirely when off,
                // so consumers can tell "not exported" from "false"/"empty".
                if ($record->isInStock() !== null) {
                    $row['in_stock'] = $record->isInStock();
                }
                if ($record->getImageUrl() !== null) {
                    $row['image'] = $record->getImageUrl();
                }
                if ($record->getAttributes() !== []) {
                    $row['attributes'] = $record->getAttributes();
                }

                $row['embedding_text'] = mb_substr(
                    trim($record->getName() . "\n" . $short . "\n" . $desc),
                    0,
                    self::EMBED_MAX
                );

                yield $this->encodeJsonl($row);
                return;
        }
    }

    public function finish(OutputContextInterface $context): iterable
    {
        return [];
    }

    /**
     * Markdown-mirror URL for the record, or null when mirrors are not served.
     * JSONL keeps the canonical `url` untouched and adds `md_url` beside it —
     * replacing `url` would break every consumer already indexing the feed.
     */
    private function mdUrl(?string $url, OutputContextInterface $context): ?string
    {
        $md = $this->publicUrl($url, $context);

        return ($md === $url) ? null : $md;
    }
}
