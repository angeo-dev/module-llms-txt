<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Url;

/**
 * Origin (scheme://host[:port]) of an absolute URL.
 *
 * /.well-known/ paths live at the domain root (RFC 8615). They must not get
 * the store code or index.php that a store's link base URL can carry.
 *
 * @since 5.1.0
 */
class Origin
{
    public static function fromUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($url, '/');
        }

        return $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
