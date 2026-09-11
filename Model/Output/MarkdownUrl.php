<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

/**
 * Turns a storefront URL into the URL of its markdown mirror.
 *
 * llms.txt v2 (August 2026) allows two forms: `.md` appended to the full page
 * URL (`page.html.md`) and the extension replaced (`page.md`). This module
 * appends, because Magento's configurable URL suffix means the stored request
 * path already carries `.html` and appending keeps the mapping back to the
 * url_rewrite row exact. {@see \Angeo\LlmsTxt\Controller\Index\MdMirror}
 * resolves both forms.
 *
 * @api
 * @since 4.0.0
 */
class MarkdownUrl
{
    public const SUFFIX = '.md';

    /**
     * @param string|null $url Absolute storefront URL.
     * @return string|null The mirror URL, or the input unchanged when it has
     *                     no path to mirror (store root) or already ends in .md.
     */
    public function forUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }
        if (str_ends_with($url, self::SUFFIX)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '');
        if (trim($path, '/') === '') {
            // Store root has no entity page to mirror.
            return $url;
        }

        $path = rtrim($path, '/');

        return $this->rebuild($parts, $path . self::SUFFIX);
    }

    /**
     * @param array<string, string|int> $parts Result of parse_url().
     */
    private function rebuild(array $parts, string $path): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host   = (string) ($parts['host'] ?? '');
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . $host . $port . $path;
    }
}
