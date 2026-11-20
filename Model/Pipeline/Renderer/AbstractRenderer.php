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
use Angeo\LlmsTxt\Model\Text\MarkdownText;
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
    /** Stateless helper; not injected so subclass constructors stay unchanged. */
    protected readonly MarkdownText $markdown;

    public function __construct(
        protected readonly Truncator $truncator,
        protected readonly ?Config $linkConfig = null,
        protected readonly ?MarkdownUrl $markdownUrl = null
    ) {
        $this->markdown = new MarkdownText();
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
     * Escape text for a single-line markdown position (link label, heading).
     *
     * 5.0.0: also escapes the backslash itself and folds line breaks — a
     * trailing "\" used to cancel the closing "]", and a newline in a product
     * name started a new markdown block.
     */
    protected function escapeMarkdown(string $text): string
    {
        return $this->markdown->inline($text);
    }

    /**
     * Body text for a multi-line position (see {@see MarkdownText::block()}).
     *
     * @since 5.0.0
     */
    protected function safeBlock(string $text): string
    {
        return $this->markdown->block($text);
    }

    /**
     * Body text for a position after "- [Name](url): " — must stay on the
     * list item's line.
     *
     * @since 5.0.0
     */
    protected function safeInlineText(string $text): string
    {
        return $this->markdown->oneLine($this->markdown->block($text));
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
