<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Text;

/**
 * Makes catalog text safe to place inside generated markdown.
 *
 * Product names and descriptions often come from semi-trusted sources (PIM,
 * supplier feeds, marketplace vendors). Before 5.0.0 that text went into
 * llms.txt, llms-full.txt and the .md mirrors unescaped, so a description
 * could add its own links (including javascript: URLs), headings or
 * blockquotes — and speak with the store's voice to every AI agent that reads
 * the file. This class keeps the text and removes the markdown structure.
 *
 * JSONL is not affected: it is JSON, not markdown.
 *
 * @api
 * @since 5.0.0
 */
class MarkdownText
{
    /**
     * Text for a single-line position: link label, heading, "Key: value".
     * Line breaks become spaces; markdown link and table syntax is escaped.
     */
    public function inline(string $text): string
    {
        return strtr($this->oneLine($text), [
            '\\' => '\\\\',
            '['  => '\\[',
            ']'  => '\\]',
            '('  => '\\(',
            ')'  => '\\)',
            '|'  => '\\|',
            '`'  => '\\`',
        ]);
    }

    /**
     * Multi-line body text. Paragraphs are kept; links become their label;
     * lines that would start a heading, quote, fence or rule are escaped.
     */
    public function block(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // [label](url) and ![alt](url) → label / alt.
        // One level of nested parentheses is allowed in the URL part, as in
        // CommonMark: (javascript:alert(1)).
        $text = preg_replace('/!?\[([^\]\n]*)\]\((?:[^()\n]|\([^()\n]*\))*\)/u', '$1', $text) ?? $text;
        // [label][ref] → label
        $text = preg_replace('/\[([^\]\n]+)\]\[[^\]\n]*\]/u', '$1', $text) ?? $text;
        // Reference definitions: "[ref]: https://…"
        $text = preg_replace('/^[ \t]{0,3}\[[^\]\n]+\]:[^\n]*$/mu', '', $text) ?? $text;
        // Autolinks such as <https://…> or <javascript:…> → inner text.
        $text = preg_replace('/<([a-z][a-z0-9+.\-]*:[^<>\s]*)>/iu', '$1', $text) ?? $text;
        // Any bracket left over must not open a link.
        $text = strtr($text, ['[' => '\\[', ']' => '\\]']);

        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '') {
                continue;
            }
            if (
                preg_match('/^(#|>|```|~~~)/u', $trimmed) === 1
                || preg_match('/^([-=*_])(\s*\1){2,}\s*$/u', $trimmed) === 1
            ) {
                $lines[$i] = '\\' . $trimmed;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Collapse every run of whitespace (line breaks included) into one space.
     */
    public function oneLine(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
