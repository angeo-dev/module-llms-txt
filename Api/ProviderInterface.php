<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Api;

use Magento\Store\Api\Data\StoreInterface;

/**
 * Content provider contract for both llms.txt and JSONL generators.
 */
interface ProviderInterface
{
    /**
     * Generate content for the given store.
     *
     * @param StoreInterface $store
     * @return string  Formatted content block (markdown for llms.txt, JSONL lines for JSONL)
     */
    public function provide(StoreInterface $store): string;
}
