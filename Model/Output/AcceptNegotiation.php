<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

/**
 * Does an Accept header ask for markdown rather than HTML?
 *
 * "Markdown for Agents": an agent sends `Accept: text/markdown` and gets the
 * page as markdown, while browsers (which send text/html) keep getting HTML.
 * Markdown wins only when its q-value is above zero and higher than the one
 * HTML gets (text/html, else text/*, else *\/*). A tie goes to HTML, so a
 * client that accepts both equally is never surprised.
 *
 * @since 5.2.0
 */
class AcceptNegotiation
{
    public static function prefersMarkdown(string $accept): bool
    {
        $accept = trim($accept);
        if ($accept === '' || stripos($accept, 'markdown') === false || strlen($accept) > 2048) {
            return false;
        }
        $q = self::qualities($accept);
        $md = $q['text/markdown'] ?? $q['text/x-markdown'] ?? 0.0;
        $html = $q['text/html'] ?? $q['application/xhtml+xml'] ?? $q['text/*'] ?? $q['*/*'] ?? 0.0;

        return $md > 0.0 && $md > $html;
    }

    /** @return array<string,float> media range => q */
    private static function qualities(string $accept): array
    {
        $out = [];
        foreach (explode(',', $accept) as $part) {
            $bits = array_map('trim', explode(';', $part));
            $type = strtolower((string) array_shift($bits));
            if ($type === '') {
                continue;
            }
            $q = 1.0;
            foreach ($bits as $param) {
                if (preg_match('/^q\s*=\s*([01](?:\.\d{0,3})?)$/i', $param, $m) === 1) {
                    $q = (float) $m[1];
                }
            }
            $out[$type] = max($out[$type] ?? 0.0, min(1.0, $q));
        }

        return $out;
    }
}
