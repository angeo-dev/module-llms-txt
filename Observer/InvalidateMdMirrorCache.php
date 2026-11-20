<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Observer;

use Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Pipeline\ChangeMarker;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Psr\Log\LoggerInterface;

/**
 * Drops the cached markdown mirror of one entity when it is saved or deleted.
 *
 * Before 4.1.0 mirrors were only invalidated wholesale after a generation run,
 * so an edited product kept serving its previous markdown until the HTTP cache
 * TTL expired — up to an hour by default, and a whole day if the merchant had
 * raised the TTL. Cached HTML gets invalidated on save; the markdown twin of
 * the same page should behave the same way.
 *
 * Only the affected entity's keys are removed, never the whole tag: cleaning
 * the tag on every product save would throw away the entire mirror cache
 * during an import.
 *
 * @since 4.1.0
 */
class InvalidateMdMirrorCache implements ObserverInterface
{
    /**
     * Event data keys that may carry the entity. 5.0.0: CMS pages have no
     * $_eventObject, so cms_page_* events carry the page under "object" /
     * "data_object" — the old "page" key never matched and CMS mirrors were
     * never invalidated.
     */
    private const EVENT_KEYS = ['product', 'category', 'object', 'data_object', 'page'];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UrlFinderInterface $urlFinder,
        private readonly StoreManagerInterface $storeManager,
        private readonly MdMirrorCacheKey $cacheKey,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly ChangeMarker $changeMarker
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            [$entityId, $entityType] = $this->resolveEntity($observer);
            if ($entityId === null) {
                return;
            }

            // Deletes are invisible to ChangeDetector's timestamps (5.0.0).
            if (str_ends_with((string) $observer->getEvent()->getName(), '_delete_after')) {
                $this->changeMarker->markDeleted();
            }

            foreach ($this->storeManager->getStores() as $store) {
                if (!$this->config->isMdMirrorEnabled($store)) {
                    continue;
                }
                if (!$this->config->isInvalidateMirrorOnSaveEnabled($store)) {
                    continue;
                }

                $rewrites = $this->urlFinder->findAllByData([
                    UrlRewrite::ENTITY_ID   => $entityId,
                    UrlRewrite::ENTITY_TYPE => $entityType,
                    UrlRewrite::STORE_ID    => (int) $store->getId(),
                ]);

                foreach ($rewrites as $rewrite) {
                    // Every rewrite the entity ever had, including old ones
                    // kept as redirects — each could hold a cached mirror,
                    // under every path form the controller accepts.
                    foreach ($this->pathVariants((string) $rewrite->getRequestPath()) as $path) {
                        $this->cache->remove($this->cacheKey->forPath((int) $store->getId(), $path));
                    }
                }
            }
        } catch (\Throwable $e) {
            // A cache miss is cheap; a failed save is not. Never let this throw
            // out of an entity save.
            $this->logger->warning(
                '[Angeo LlmsTxt] Markdown mirror invalidation failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resolveEntity(Observer $observer): array
    {
        foreach (self::EVENT_KEYS as $eventKey) {
            $entity = $observer->getEvent()->getData($eventKey);
            if (!is_object($entity) || !method_exists($entity, 'getId') || !$entity->getId()) {
                continue;
            }
            $type = match (true) {
                $entity instanceof ProductInterface  => 'product',
                $entity instanceof CategoryInterface => 'category',
                $entity instanceof PageInterface     => 'cms-page',
                default                              => null,
            };
            if ($type !== null) {
                return [(int) $entity->getId(), $type];
            }
        }

        return [null, ''];
    }

    /**
     * Cache keys the controller may have used for one rewrite: the stored
     * path ("shirt.html" ← /shirt.html.md), without its suffix
     * ("shirt" ← /shirt.md) and without a trailing slash ("shoes" ← /shoes.md
     * for a "shoes/" rewrite).
     *
     * @return string[]
     */
    private function pathVariants(string $requestPath): array
    {
        $paths = [$requestPath];
        $trimmed = rtrim($requestPath, '/');
        if ($trimmed !== $requestPath && $trimmed !== '') {
            $paths[] = $trimmed;
        }
        if (str_ends_with($trimmed, '.html')) {
            $paths[] = substr($trimmed, 0, -5);
        }

        return array_values(array_unique($paths));
    }
}
