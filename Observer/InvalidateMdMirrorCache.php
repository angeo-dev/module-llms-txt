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
    /** Event object key => url_rewrite entity type. */
    private const ENTITY_TYPES = [
        'product'  => 'product',
        'category' => 'category',
        'page'     => 'cms-page',
    ];

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly UrlFinderInterface $urlFinder,
        private readonly StoreManagerInterface $storeManager,
        private readonly MdMirrorCacheKey $cacheKey,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        try {
            [$entityId, $entityType] = $this->resolveEntity($observer);
            if ($entityId === null) {
                return;
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
                    // kept as redirects — each could hold a cached mirror.
                    $this->cache->remove(
                        $this->cacheKey->forPath((int) $store->getId(), $rewrite->getRequestPath())
                    );
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
        foreach (self::ENTITY_TYPES as $eventKey => $urlRewriteType) {
            $entity = $observer->getEvent()->getData($eventKey);
            if ($entity !== null && method_exists($entity, 'getId') && $entity->getId()) {
                return [(int) $entity->getId(), $urlRewriteType];
            }
        }

        return [null, ''];
    }
}
