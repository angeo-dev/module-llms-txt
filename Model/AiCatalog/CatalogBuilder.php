<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\AiCatalog;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Store\Api\Data\StoreInterface;

/**
 * The store's AI Catalog (https://github.com/Agent-Card/ai-catalog, draft
 * specVersion 1.0), served at /.well-known/ai-catalog.json.
 *
 * This module does not decide what the store offers to agents. It asks:
 * event `angeo_ai_catalog_collect` with
 *   - store   StoreInterface
 *   - catalog DataObject, `entries` = list of AI Catalog entries
 * and the modules that run those services add themselves — for example
 * angeo/module-mcp-server 2.5+ adds its MCP Server Card. No Composer
 * dependency in either direction.
 *
 * Every entry is checked before it is published: `identifier` and `type`
 * are strings, exactly one of `url` (https) or `data`, unknown members are
 * dropped, identifiers are unique. No valid entry → no catalog (404): an
 * empty catalog tells agents nothing.
 *
 * @api
 * @since 5.1.0
 */
class CatalogBuilder
{
    public const EVENT = 'angeo_ai_catalog_collect';
    public const MEDIA_TYPE = 'application/ai-catalog+json';
    public const SPEC_VERSION = '1.0';
    private const ENTRY_FIELDS = ['identifier', 'type', 'url', 'data', 'displayName', 'description', 'tags', 'version', 'updatedAt'];
    private const MAX_ENTRIES = 50;

    public function __construct(
        private readonly EventManager $eventManager
    ) {
    }

    /** @return array<string,mixed>|null null when there is nothing to publish */
    public function build(StoreInterface $store): ?array
    {
        $transport = new DataObject(['entries' => []]);
        $this->eventManager->dispatch(self::EVENT, ['store' => $store, 'catalog' => $transport]);

        $base = (string) $store->getBaseUrl();
        $host = (string) parse_url($base, PHP_URL_HOST);

        return self::assemble((array) $transport->getData('entries'), self::hostName($store), $host);
    }

    /**
     * Pure part, unit tested.
     *
     * @param array<mixed> $rawEntries
     * @return array<string,mixed>|null
     */
    public static function assemble(array $rawEntries, string $displayName, string $domain): ?array
    {
        $entries = [];
        foreach ($rawEntries as $raw) {
            $entry = self::clean($raw);
            if ($entry === null || isset($entries[$entry['identifier']])) {
                continue;
            }
            $entries[$entry['identifier']] = $entry;
            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }
        if ($entries === []) {
            return null;
        }

        $catalog = ['specVersion' => self::SPEC_VERSION];
        $hostInfo = ['displayName' => $displayName !== '' ? $displayName : ($domain !== '' ? $domain : 'Store')];
        if ($domain !== '') {
            $hostInfo['identifier'] = $domain;
        }
        $catalog['host'] = $hostInfo;
        $catalog['entries'] = array_values($entries);

        return $catalog;
    }

    /** @return array<string,mixed>|null */
    private static function clean(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $e = array_intersect_key($raw, array_flip(self::ENTRY_FIELDS));
        if (!is_string($e['identifier'] ?? null) || trim($e['identifier']) === ''
            || !is_string($e['type'] ?? null) || trim($e['type']) === '') {
            return null;
        }
        $hasUrl = array_key_exists('url', $e);
        $hasData = array_key_exists('data', $e);
        if ($hasUrl === $hasData) {
            return null; // exactly one of url or data
        }
        if ($hasUrl && (!is_string($e['url']) || !str_starts_with($e['url'], 'https://')
            || filter_var($e['url'], FILTER_VALIDATE_URL) === false)) {
            return null;
        }
        foreach (['displayName', 'description', 'version', 'updatedAt'] as $k) {
            if (isset($e[$k]) && !is_string($e[$k])) {
                unset($e[$k]);
            }
        }
        if (isset($e['tags']) && (!is_array($e['tags']) || array_filter($e['tags'], 'is_string') !== $e['tags'])) {
            unset($e['tags']);
        }

        return $e;
    }

    private static function hostName(StoreInterface $store): string
    {
        foreach ([fn () => $store->getFrontendName(), fn () => $store->getGroup()->getName(), fn () => $store->getName()] as $get) {
            try {
                $name = trim((string) $get());
                if ($name !== '' && !in_array(mb_strtolower($name), ['default store view', 'main website store', 'main website', 'default'], true)) {
                    return $name;
                }
            } catch (\Throwable) {
                // next
            }
        }

        return '';
    }
}
