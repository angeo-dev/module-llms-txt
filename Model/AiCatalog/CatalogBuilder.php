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
 * 5.2.0 — ARD v0.91 (agenticresourcediscovery.org) reads the same document
 * from /.well-known/ard.json: it needs only `entries` and ignores the other
 * top-level members. ARD requires `displayName` on every entry (AI Catalog
 * makes it optional), so an entry without one gets the host's name, and
 * `representativeQueries` (2–5 strings) are kept.
 *
 * @api
 * @since 5.1.0
 */
class CatalogBuilder
{
    public const EVENT = 'angeo_ai_catalog_collect';
    public const MEDIA_TYPE = 'application/ai-catalog+json';
    public const SPEC_VERSION = '1.0';
    private const ENTRY_FIELDS = ['identifier', 'type', 'url', 'data', 'displayName', 'description', 'tags', 'version', 'updatedAt', 'representativeQueries'];
    /** ARD v0.91: 2–5 example queries per entry. @since 5.2.0 */
    private const MAX_QUERIES = 5;
    /** @since 5.2.0 */
    public const ARD_PATH = '/.well-known/ard.json';
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
        foreach ($entries as $id => $entry) {
            if (!isset($entry['displayName']) || trim($entry['displayName']) === '') {
                $entries[$id]['displayName'] = $hostInfo['displayName'];
            }
        }
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
        if (array_key_exists('representativeQueries', $e)) {
            $queries = self::queries($e['representativeQueries']);
            if ($queries === []) {
                unset($e['representativeQueries']);
            } else {
                $e['representativeQueries'] = $queries;
            }
        }

        return $e;
    }

    /**
     * Up to five distinct, non-empty strings, or none when fewer than two
     * remain (ARD asks for 2–5).
     *
     * @return list<string>
     */
    private static function queries(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $q) {
            if (!is_string($q)) {
                continue;
            }
            $q = trim(mb_substr($q, 0, 200));
            if ($q !== '' && !in_array($q, $out, true)) {
                $out[] = $q;
            }
            if (count($out) >= self::MAX_QUERIES) {
                break;
            }
        }

        return count($out) >= 2 ? $out : [];
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
