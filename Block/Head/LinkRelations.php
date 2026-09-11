<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Block\Head;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\MarkdownUrl;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Emits the llms.txt v2 link relations into the storefront <head>:
 *
 *   <link rel="alternate" type="text/markdown" href="…/page.html.md">
 *   <link rel="describedby" href="…/llms.txt">
 *
 * v2 added these to answer the question agents kept asking: given a page, how
 * do I find its markdown version and the llms.txt that covers it, without
 * guessing URLs? The block is attached to the product, category and CMS page
 * handles only — those are the entities the mirror controller can render.
 *
 * The page URL is taken from the ORIGINAL path info, not from the rewritten
 * internal route, because that is exactly the key the mirror controller looks
 * up in url_rewrite. It also keeps the store-code path segment intact on
 * multi-store setups.
 *
 * Theme-independent: the output is two <link> tags with no assets behind them,
 * so it works unchanged in Luma and Hyvä.
 *
 * @since 4.0.0
 */
class LinkRelations extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly MarkdownUrl $markdownUrl,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Absolute URL of this page's markdown mirror, or null when unavailable.
     */
    public function getMarkdownUrl(): ?string
    {
        $path = trim((string) $this->_request->getOriginalPathInfo(), '/');
        if ($path === '') {
            return null;
        }

        return $this->markdownUrl->forUrl($this->baseUrl() . $path);
    }

    /**
     * Absolute URL of the llms.txt that describes this page.
     */
    public function getLlmsTxtUrl(): string
    {
        return $this->baseUrl() . 'llms.txt';
    }

    protected function _toHtml(): string
    {
        try {
            $store = $this->storeManager->getStore();
        } catch (\Throwable) {
            return '';
        }

        if (
            !$this->config->isEnabled($store)
            || $this->config->isStoreExcluded($store)
            || !$this->config->isMdMirrorEnabled($store)
            || !$this->config->isLinkRelationsEnabled($store)
        ) {
            return '';
        }

        if ($this->getMarkdownUrl() === null) {
            return '';
        }

        return parent::_toHtml();
    }

    private function baseUrl(): string
    {
        try {
            return rtrim($this->storeManager->getStore()->getBaseUrl(), '/') . '/';
        } catch (\Throwable) {
            return '/';
        }
    }
}
