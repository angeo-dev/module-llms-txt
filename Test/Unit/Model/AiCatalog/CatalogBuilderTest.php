<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\AiCatalog;

use Angeo\LlmsTxt\Model\AiCatalog\CatalogBuilder;
use PHPUnit\Framework\TestCase;

/**
 * 5.1.0: what goes into /.well-known/ai-catalog.json.
 */
class CatalogBuilderTest extends TestCase
{
    private const MCP = [
        'identifier' => 'urn:air:shop.example.com:mcp:mcp',
        'type' => 'application/mcp-server-card+json',
        'url' => 'https://shop.example.com/mcp/server-card',
    ];

    public function testNoEntriesMeansNoCatalog(): void
    {
        self::assertNull(CatalogBuilder::assemble([], 'Shop', 'shop.example.com'));
    }

    public function testMinimalDiscoverableCatalog(): void
    {
        $catalog = CatalogBuilder::assemble([self::MCP], 'Example Shop', 'shop.example.com');

        self::assertSame('1.0', $catalog['specVersion']);
        self::assertSame(['displayName' => 'Example Shop', 'identifier' => 'shop.example.com'], $catalog['host']);
        // 5.2.0: ARD needs displayName on every entry; the host name fills it.
        self::assertSame([self::MCP + ['displayName' => 'Example Shop']], $catalog['entries']);
    }

    public function testArdFieldsAreKept(): void
    {
        $catalog = CatalogBuilder::assemble([self::MCP + [
            'displayName' => 'Shop MCP',
            'representativeQueries' => [' find a tee ', 'find a tee', 3, 'check stock', 'a', 'b', 'c', 'd'],
        ]], 'Shop', 'shop.example.com');
        $entry = $catalog['entries'][0];

        self::assertSame('Shop MCP', $entry['displayName']);
        self::assertSame(['find a tee', 'check stock', 'a', 'b', 'c'], $entry['representativeQueries']);
    }

    public function testOneQueryIsNotEnough(): void
    {
        $catalog = CatalogBuilder::assemble([self::MCP + ['representativeQueries' => ['only one']]], 'Shop', 'shop.example.com');

        self::assertArrayNotHasKey('representativeQueries', $catalog['entries'][0]);
    }

    public function testInvalidEntriesAreDropped(): void
    {
        $catalog = CatalogBuilder::assemble([
            'not an array',
            ['type' => 'x', 'url' => 'https://a.example/'],                                   // no identifier
            ['identifier' => 'a', 'url' => 'https://a.example/'],                             // no type
            ['identifier' => 'b', 'type' => 'x'],                                             // neither url nor data
            ['identifier' => 'c', 'type' => 'x', 'url' => 'https://a.example/', 'data' => []], // both
            ['identifier' => 'd', 'type' => 'x', 'url' => 'http://a.example/'],               // not https
            ['identifier' => 'e', 'type' => 'x', 'url' => 'javascript:alert(1)'],
            self::MCP,
            self::MCP,                                                                        // duplicate identifier
        ], 'Shop', 'shop.example.com');

        self::assertCount(1, $catalog['entries']);
        self::assertSame(self::MCP['identifier'], $catalog['entries'][0]['identifier']);
    }

    public function testUnknownMembersAndBadTypesAreRemoved(): void
    {
        $catalog = CatalogBuilder::assemble([self::MCP + [
            'secret' => 'no', 'displayName' => ['x'], 'tags' => ['ok', 3], 'description' => 'Store MCP',
        ]], 'Shop', 'shop.example.com');
        $entry = $catalog['entries'][0];

        self::assertArrayNotHasKey('secret', $entry);
        self::assertSame('Shop', $entry['displayName']); // bad value dropped, host name used (5.2.0)
        self::assertArrayNotHasKey('tags', $entry);
        self::assertSame('Store MCP', $entry['description']);
    }

    public function testInlineDataIsAllowed(): void
    {
        $catalog = CatalogBuilder::assemble([['identifier' => 'urn:air:shop.example.com:agent:x',
            'type' => 'application/a2a-agent-card+json', 'data' => ['name' => 'x']]], '', '');

        self::assertSame(['name' => 'x'], $catalog['entries'][0]['data']);
        self::assertSame(['displayName' => 'Store'], $catalog['host']);
    }
}
