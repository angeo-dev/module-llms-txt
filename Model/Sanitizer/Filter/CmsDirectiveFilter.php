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
use Angeo\LlmsTxt\Model\Config;
use Magento\Cms\Model\Template\FilterProvider;
use Psr\Log\LoggerInterface;

/**
 * Resolves Magento CMS directives ({{widget ...}}, {{block ...}}, {{var ...}},
 * {{store ...}}, etc.) by passing the content through Magento's standard
 * {@see FilterProvider::getPageFilter()}.
 *
 * Without this filter, directives appear in llms.txt as literal text — leaking
 * Magento template internals to AI crawlers and breaking embeddings.
 *
 * Runs inside the frontend-emulation scope set up by the pipeline, so
 * widgets resolve to what visitors actually see.
 *
 * SECURITY (3.1.0): product attribute content (descriptions) frequently comes
 * from semi-trusted sources — supplier feeds, PIM imports, marketplace syncs.
 * Resolving {{block}}/{{widget}} directives in that content lets whoever
 * controls the feed pull the rendered output of arbitrary blocks into a
 * publicly served file (template-directive injection). Directive resolution
 * for product content is therefore gated behind a SEPARATE config flag
 * (`angeo_llms/sanitizer/resolve_directives_products`, default OFF). When the
 * flag is off, directives in product content are removed instead of resolved,
 * so neither template internals nor block output leaks.
 *
 * Controlled by config:
 *  - `angeo_llms/sanitizer/resolve_directives` (CMS pages / categories, default = yes)
 *  - `angeo_llms/sanitizer/resolve_directives_products` (products, default = NO)
 *
 * @since 3.0.0
 */
class CmsDirectiveFilter implements SanitizerFilterInterface
{
    public function __construct(
        private readonly FilterProvider $filterProvider,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function filter(string $content, OutputContextInterface $context): string
    {
        // Cheap pre-check — avoid spinning up the filter when there's nothing to resolve.
        if (!str_contains($content, '{{')) {
            return $content;
        }

        $store = $context->getStore();
        $entityType = (string) $context->getShared(OutputContextInterface::SHARED_ENTITY_TYPE);

        $resolutionAllowed = $this->config->shouldResolveDirectives($store)
            && ($entityType !== 'product' || $this->config->shouldResolveProductDirectives($store));

        if (!$resolutionAllowed) {
            // Do NOT pass semi-trusted content through the template filter; and do
            // NOT leak directive source either — strip the directives instead.
            return $this->stripDirectives($content);
        }

        try {
            $filter = $this->filterProvider->getPageFilter();
            $filter->setStoreId((int) $store->getId());
            return (string) $filter->filter($content);
        } catch (\Throwable $e) {
            // CMS filter throws on malformed directives; strip them rather than
            // leaking raw directive source into the public output.
            $this->logger->info(sprintf(
                '[Angeo LlmsTxt] CmsDirectiveFilter could not resolve directives in store %s: %s',
                $store->getCode(),
                $e->getMessage()
            ));
            return $this->stripDirectives($content);
        }
    }

    /**
     * Remove {{...}} directive source without executing it.
     */
    private function stripDirectives(string $content): string
    {
        return preg_replace('/\{\{[^{}]*\}\}/s', '', $content) ?? $content;
    }
}
