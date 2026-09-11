<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Output;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\Signature;
use PHPUnit\Framework\TestCase;

/**
 * Locks down the attribution-signature contract:
 *  - separated from preceding content by a newline + markdown horizontal rule,
 *  - single paragraph, newline-terminated (POSIX-friendly EOF),
 *  - carries the current module version,
 *  - contains no JSON-breaking control characters (it must never be able to
 *    corrupt a stream even if mis-wired),
 *  - txt and md-mirror variants differ only in utm_medium (measurable surfaces).
 */
class SignatureTest extends TestCase
{
    private Signature $signature;

    protected function setUp(): void
    {
        $this->signature = new Signature();
    }

    public function testTxtSignatureIsMarkdownFooter(): void
    {
        $sig = $this->signature->forTxt();

        self::assertStringStartsWith("\n---\n\n", $sig, 'Must open with an hr separated from content');
        self::assertStringEndsWith("\n", $sig, 'Must end with a newline');
        self::assertStringContainsString('Angeo LlmsTxt', $sig);
        self::assertStringContainsString('https://angeo.dev/', $sig);
    }

    public function testTxtSignatureCarriesModuleVersion(): void
    {
        self::assertStringContainsString(
            'v' . Config::MODULE_VERSION,
            $this->signature->forTxt()
        );
    }

    public function testSignatureIsSingleTrailingParagraph(): void
    {
        // Exactly one hr and one paragraph — no multi-block footer creep.
        $body = ltrim($this->signature->forTxt(), "\n");
        $blocks = preg_split('/\n{2,}/', trim($body));
        self::assertCount(2, $blocks, 'hr + one paragraph only');
        self::assertSame('---', $blocks[0]);
    }

    public function testSignatureContainsNoControlCharacters(): void
    {
        $stripped = str_replace("\n", '', $this->signature->forTxt());
        self::assertSame(
            0,
            preg_match('/[\x00-\x1F\x7F]/', $stripped),
            'No control chars besides newlines — must stay stream-safe'
        );
    }

    public function testAgentsMdVariantDiffersOnlyInUtmMedium(): void
    {
        self::assertSame(
            str_replace('utm_medium=signature', 'utm_medium=agents-md', $this->signature->forTxt()),
            $this->signature->forAgentsMd(),
            'agents.md variant must stay in lock-step apart from the tracking medium'
        );
    }

    public function testMirrorVariantDiffersOnlyInUtmMedium(): void
    {
        $txt    = $this->signature->forTxt();
        $mirror = $this->signature->forMdMirror();

        self::assertNotSame($txt, $mirror);
        self::assertSame(
            str_replace('utm_medium=signature', 'utm_medium=md-mirror', $txt),
            $mirror,
            'Variants must stay in lock-step apart from the tracking medium'
        );
    }
}
