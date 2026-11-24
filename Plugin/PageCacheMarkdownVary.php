<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Plugin;

use Angeo\LlmsTxt\Model\Output\AcceptNegotiation;
use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Built-in full page cache: a request that prefers markdown gets its own
 * cache key, so it is never answered with the cached HTML page and the HTML
 * page is never replaced by markdown. (Markdown answers are `private` and
 * never stored.) Varnish and Fastly need the VCL change in the README.
 *
 * @since 5.2.0
 */
class PageCacheMarkdownVary
{
    public function __construct(private readonly HttpRequest $request)
    {
    }

    public function afterGetValue(Identifier $subject, string $result): string
    {
        return AcceptNegotiation::prefersMarkdown((string) $this->request->getHeader('Accept'))
            ? $result . '-md'
            : $result;
    }
}
