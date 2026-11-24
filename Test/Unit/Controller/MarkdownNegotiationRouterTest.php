<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Controller;

use Angeo\LlmsTxt\Block\Head\AiCatalogLink;
use Angeo\LlmsTxt\Controller\MarkdownNegotiationRouter;
use PHPUnit\Framework\TestCase;

/**
 * 5.2.0: home page identifier and the ARD link.
 */
class MarkdownNegotiationRouterTest extends TestCase
{
    public function testHomeIdentifier(): void
    {
        self::assertSame('home', MarkdownNegotiationRouter::homeIdentifier('home|2'));
        self::assertSame('home', MarkdownNegotiationRouter::homeIdentifier('home'));
        self::assertSame('', MarkdownNegotiationRouter::homeIdentifier(''));
    }

    public function testArdUrl(): void
    {
        self::assertSame(
            'https://s.example/.well-known/ard.json',
            AiCatalogLink::ardUrlFor('https://s.example/.well-known/ai-catalog.json')
        );
    }
}
