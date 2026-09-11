<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Renderer;

use Angeo\LlmsTxt\Api\FormatRendererInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\MarkdownUrl;
use Angeo\LlmsTxt\Model\Text\Truncator;

/**
 * Shared helpers for format renderers — markdown escaping, JSONL encoding,
 * word-boundary truncation. Output rules replicate the legacy providers
 * byte-for-byte.
 *
 * @since 3.2.0
 */
abstract class AbstractRenderer implements FormatRendererInterface
{
    public function __construct(
        protected readonly Truncator $truncator,
        protected readonly ?Config $linkConfig = null,
        protected readonly ?MarkdownUrl $markdownUrl = null
    ) {
    }

    /**
     * The URL to publish for an entity.
     *
     * llms.txt v2: "the links in an llms.txt file should point to LLM-friendly
     * content, such as the markdown versions of pages". When the merchant
     * serves mirrors and has not opted out, that is the .md URL; otherwise the
     * canonical HTML URL, exactly as before.
     *
     * @since 4.0.0
     */
    protected function publicUrl(?string $url, OutputContextInterface $context): ?string
    {
        if (
            $url === null
            || $this->linkConfig === null
            || $this->markdownUrl === null
            || !$this->linkConfig->isLinkToMdEnabled($context->getStore())
        ) {
            return $url;
        }

        return $this->markdownUrl->forUrl($url);
    }

    public function reset(): void
    {
        // Stateless by default; markdown renderers override.
    }

    /**
     * Escape markdown special characters in display text (link labels).
     * Identical character set to the legacy AbstractProvider.
     */
    protected function escapeMarkdown(string $text): string
    {
        return strtr($text, [
            '['  => '\\[',
            ']'  => '\\]',
            '('  => '\\(',
            ')'  => '\\)',
            '|'  => '\\|',
            '`'  => '\\`',
        ]);
    }

    /**
     * Encode a record as JSON, line-terminated. Identical flags to the legacy
     * AbstractProvider::encodeJsonl().
     *
     * @param array<string, mixed> $record
     */
    protected function encodeJsonl(array $record): string
    {
        $json = json_encode(
            $record,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($json === false) {
            return '';
        }
        return $json . "\n";
    }
}
