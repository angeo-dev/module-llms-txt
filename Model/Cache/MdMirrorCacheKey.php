<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Cache;

/**
 * Single source of truth for the /{url-key}.md cache key.
 *
 * Extracted in 4.1.0 because two places now compute it: the controller that
 * fills the cache and the observer that invalidates one entry on save. A
 * duplicated hashing rule would mean invalidation silently missing.
 *
 * @api
 * @since 4.1.0
 */
class MdMirrorCacheKey
{
    public const TAG = 'ANGEO_LLMS_MD';

    private const PREFIX = 'angeo_llms_md_';

    /**
     * @param string $requestPath url_rewrite request path, WITHOUT the .md
     *                            suffix and without a leading slash.
     */
    public function forPath(int $storeId, string $requestPath): string
    {
        return self::PREFIX . $storeId . '_' . hash('sha256', ltrim($requestPath, '/'));
    }
}
