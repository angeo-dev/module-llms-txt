<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerFilterInterface;

/**
 * Strips HTML, normalizes whitespace, decodes entities, trims.
 *
 * Runs AFTER {@see CmsDirectiveFilter} and {@see PageBuilderFilter} so the
 * content it sees has already had its widgets resolved and its Page Builder
 * noise removed.
 *
 * Security notes (3.1.0):
 *  - Entities are decoded BEFORE the final strip pass, then the result is
 *    tag-stripped again. Previously `&lt;script&gt;…&lt;/script&gt;` decoded
 *    into a literal `<script>` string AFTER strip_tags had already run, which
 *    could land active markup in the output. Downstream consumers that render
 *    the .md mirror as HTML would have been exposed to stored XSS.
 *  - Unterminated `<script>` / `<style>` blocks are removed up to end-of-input
 *    so inline JS (which sometimes carries analytics tokens or API keys) can
 *    never leak into the generated files.
 *
 * @since 3.0.0
 */
class HtmlFilter implements SanitizerFilterInterface
{
    public function filter(string $content, OutputContextInterface $context): string
    {
        if ($content === '') {
            return '';
        }

        $content = $this->stripActiveContent($content);

        // <br>, </p>, </div> → newline before strip_tags so paragraph structure survives.
        $content = preg_replace(
            '#</?(?:br|p|div|li|h[1-6]|tr|blockquote)\s*/?\s*>#i',
            "\n",
            $content
        ) ?? $content;

        // <!-- comments --> — Page Builder leaves a lot of these. Unterminated
        // comments are dropped to end-of-input.
        $content = preg_replace('/<!--.*?(-->|$)/s', '', $content) ?? $content;

        $content = strip_tags($content);

        // Decode HTML entities (&amp; &nbsp; &#39; etc.) …
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // … then strip again: decoding can resurrect tag-like sequences
        // (e.g. "&lt;script&gt;") that must not survive into the output.
        // 5.0.0: remove only real tag shapes ("<" + letter, "</", "<!", "<?").
        // strip_tags() here also ate plain text such as "&lt;3 years" → "",
        // because it treats a lone "<" as the start of a tag up to the end.
        $content = $this->stripActiveContent($content);
        $content = preg_replace('#<!--.*?(-->|$)#s', '', $content) ?? $content;
        $content = preg_replace('#<(?:/?[a-z][a-z0-9-]*|!|\?)[^>]*(?:>|$)#is', '', $content) ?? $content;

        // Collapse runs of whitespace, but preserve single newlines as paragraph hints.
        $content = preg_replace('/[ \t\x{00A0}]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        // Trim each line; drop empty lines at start/end.
        $lines = array_map('trim', explode("\n", $content));
        return trim(implode("\n", $lines));
    }

    /**
     * Remove <script>/<style> blocks INCLUDING their inner content, tolerating
     * a missing closing tag (removed to end-of-input).
     */
    private function stripActiveContent(string $content): string
    {
        return preg_replace(
            '#<\s*(script|style)\b[^>]*>.*?(</\s*\1\s*>|$)#is',
            '',
            $content
        ) ?? $content;
    }
}
