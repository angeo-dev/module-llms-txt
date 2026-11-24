<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Observer;

use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\AiCatalog\CatalogBuilder;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * HTTP Link header on storefront HTML pages (RFC 8288), 5.2.0.
 *
 * The <link> tags in <head> are only seen by clients that parse HTML. Agents
 * that read headers first (isitagentready.com checks this) get the same
 * pointers from the response:
 *
 *   Link: <https://shop/llms.txt>; rel="describedby"; type="text/plain",
 *         <https://shop/.well-known/ai-catalog.json>; rel="ai-catalog"
 *
 * Event `controller_front_send_response_before`, so pages served from the
 * built-in full page cache get it too. With markdown negotiation on, also
 * `Vary: Accept`. Existing Link values are kept.
 *
 * @since 5.2.0
 */
class DiscoveryLinkHeader implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly RestrictedCatalogGuard $restrictedGuard,
        private readonly CatalogBuilder $catalogBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        $response = $observer->getEvent()->getData('response');
        if (!$response instanceof HttpResponse || $response->getHttpResponseCode() !== 200) {
            return;
        }
        $type = $response->getHeader('Content-Type');
        $type = $type ? strtolower((string) $type->getFieldValue()) : '';
        if ($type !== '' && !str_starts_with($type, 'text/html')) {
            return;
        }
        try {
            $store = $this->storeManager->getStore();
            if (!$this->config->isEnabled($store) || $this->config->isStoreExcluded($store)
                || $this->restrictedGuard->isBlocked($store)) {
                return;
            }
            $base = rtrim((string) $store->getBaseUrl(), '/');
            $links = [];
            if ($this->config->isLinkRelationsEnabled($store)) {
                $links[] = sprintf('<%s/llms.txt>; rel="describedby"; type="text/plain"', $base);
            }
            if ($this->config->isAiCatalogEnabled($store) && $this->catalogBuilder->build($store) !== null) {
                $links[] = sprintf('<%s>; rel="ai-catalog"', self::originOf($base) . '/.well-known/ai-catalog.json');
            }
            $existing = $response->getHeader('Link');
            $merged = self::merge($existing ? (string) $existing->getFieldValue() : '', $links);
            if ($merged !== '') {
                $response->setHeader('Link', $merged, true);
            }
            if ($this->config->isMdNegotiationEnabled($store)) {
                $vary = $response->getHeader('Vary');
                $vary = $vary ? (string) $vary->getFieldValue() : '';
                if (preg_match('/(^|,)\s*(accept|\*)\s*(,|$)/i', $vary) !== 1) {
                    $response->setHeader('Vary', ltrim($vary . ', Accept', ', '), true);
                }
            }
        } catch (\Throwable) {
            // a header is never worth a broken page
        }
    }

    /**
     * Existing value plus the links whose target is not in it yet.
     *
     * @param list<string> $links
     */
    public static function merge(string $existing, array $links): string
    {
        $out = trim($existing) !== '' ? [trim($existing)] : [];
        foreach ($links as $link) {
            $target = substr($link, 0, (int) strpos($link, '>') + 1);
            if ($target !== '' && !str_contains($existing, $target)) {
                $out[] = $link;
            }
        }

        return implode(', ', $out);
    }

    /** "https://shop.example/de" → "https://shop.example" */
    public static function originOf(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return rtrim($baseUrl, '/');
        }

        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
