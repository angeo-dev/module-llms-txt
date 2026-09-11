<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

/**
 * Builds the agents.md document — an operator's manual addressed directly at
 * AI agents, following the storefront convention Shopify established in May
 * 2026 (adapted from the AGENTS.md standard originally used for coding
 * agents).
 *
 * Pure logic: input is a plain context array assembled by AgentsMdGenerator,
 * output is markdown. No Magento dependencies, fully unit-testable.
 *
 * Context keys (all optional except store_name/base_url):
 *   store_name, base_url, summary, locale, currency, contact_email,
 *   search_url_template, sitemap_url, agentic_sitemap_url,
 *   pages: [label => absolute URL]  (4.0.0)
 *   surfaces: [llms_txt, llms_full_txt, jsonl, md_mirror, ucp, mcp] => bool
 *
 * @since 3.4.0
 */
class AgentsMdBuilder
{
    public function build(array $ctx): string
    {
        $name    = trim((string) ($ctx['store_name'] ?? ''));
        $baseUrl = rtrim((string) ($ctx['base_url'] ?? ''), '/');
        $s       = (array) ($ctx['surfaces'] ?? []);

        $out = [];
        $out[] = '# Agent Guide: ' . $this->sanitizeInline($name);
        $out[] = '';

        $summary = trim((string) ($ctx['summary'] ?? ''));
        if ($summary !== '') {
            $out[] = '> ' . $this->sanitizeInline($summary);
            $out[] = '';
        }

        $out[] = 'This document is addressed to AI agents and automated clients. It describes';
        $out[] = 'the machine-readable surfaces this store provides and the expected rules of';
        $out[] = 'interaction. Prefer the structured surfaces below over scraping HTML: they';
        $out[] = 'are faster, current, and stable across theme changes.';
        $out[] = '';

        // ── Store facts ──────────────────────────────────────────────────────
        $out[] = '## Store';
        $out[] = '';
        $out[] = '- Canonical base URL: ' . $baseUrl . '/';
        if (!empty($ctx['locale'])) {
            $out[] = '- Locale: ' . $this->sanitizeInline((string) $ctx['locale']);
        }
        if (!empty($ctx['currency'])) {
            $out[] = '- Display currency: ' . $this->sanitizeInline((string) $ctx['currency']);
        }
        if (!empty($ctx['contact_email'])) {
            $out[] = '- Contact: ' . $this->sanitizeInline((string) $ctx['contact_email']);
        }
        $out[] = '';

        // ── Machine-readable surfaces ────────────────────────────────────────
        $out[] = '## Machine-readable surfaces';
        $out[] = '';
        if (!empty($s['llms_txt'])) {
            $out[] = sprintf('- **Content index (llms.txt):** %s/llms.txt — curated index of categories, products and pages in markdown.', $baseUrl);
        }
        if (!empty($s['llms_full_txt'])) {
            $out[] = sprintf('- **Full content (llms-full.txt):** %s/llms-full.txt — expanded descriptions for deeper context.', $baseUrl);
        }
        if (!empty($s['jsonl'])) {
            $out[] = sprintf('- **Structured dataset (JSONL):** %s/llms.jsonl — one JSON record per entity; preferred for programmatic consumption.', $baseUrl);
        }
        if (!empty($s['md_mirror'])) {
            $out[] = sprintf('- **Markdown mirrors:** append `.md` to a product or category URL path for a markdown rendition of that page (e.g. %s/example-product.md).', $baseUrl);
        }
        if (!empty($s['ucp'])) {
            $out[] = sprintf('- **UCP profile:** %s/.well-known/ucp — Universal Commerce Protocol discovery manifest declaring this store\'s commerce capabilities and signing keys.', $baseUrl);
        }
        if (!empty($s['mcp'])) {
            $out[] = sprintf('- **MCP endpoint:** POST %s/mcp — Model Context Protocol server (JSON-RPC 2.0, Streamable HTTP). Live catalog search, product cards, categories and store info. Start with an `initialize` request; then `tools/list`.', $baseUrl);
        }
        if (!empty($ctx['sitemap_url'])) {
            $out[] = '- **Sitemap:** ' . $this->sanitizeInline((string) $ctx['sitemap_url']);
        }
        if (!empty($ctx['agentic_sitemap_url'])) {
            $out[] = '- **Agent discovery sitemap:** '
                . $this->sanitizeInline((string) $ctx['agentic_sitemap_url'])
                . ' — declares the files on this page.';
        }
        $out[] = '';

        // ── Key pages and policies ───────────────────────────────────────────
        // An agent that is about to recommend or transact needs the delivery,
        // returns and privacy terms as links it can quote, not as prose it has
        // to find. Skipped entirely when the merchant configured nothing, so
        // the file never carries an empty heading.
        $pages = array_filter((array) ($ctx['pages'] ?? []));
        if ($pages !== []) {
            $out[] = '## Key pages';
            $out[] = '';
            foreach ($pages as $label => $url) {
                $out[] = sprintf(
                    '- **%s:** %s',
                    $this->sanitizeInline((string) $label),
                    $this->sanitizeInline((string) $url)
                );
            }
            $out[] = '';
            $out[] = 'These pages are authoritative for this store\'s terms. Quote them rather';
            $out[] = 'than summarising from memory, and re-fetch them before stating a policy.';
            $out[] = '';
        }

        // ── How to search ────────────────────────────────────────────────────
        if (!empty($ctx['search_url_template'])) {
            $out[] = '## Searching the catalog';
            $out[] = '';
            if (!empty($s['mcp'])) {
                $out[] = 'Preferred: the MCP `search_products` tool (live prices and stock).';
                $out[] = 'Fallback: the storefront search URL template below.';
            } else {
                $out[] = 'Use the storefront search URL template:';
            }
            $out[] = '';
            $out[] = '```';
            $out[] = (string) $ctx['search_url_template'];
            $out[] = '```';
            $out[] = '';
        }

        // ── Rules of interaction ─────────────────────────────────────────────
        $out[] = '## Rules of interaction';
        $out[] = '';
        $out[] = '- Respect robots.txt; it remains authoritative for crawl permissions.';
        $out[] = '- Identify yourself with a descriptive User-Agent.';
        $out[] = '- Prices and availability change: for purchase decisions use the live surfaces'
            . (empty($s['mcp']) ? ' and re-fetch product pages rather than relying on cached copies.' : ' (MCP) rather than cached file content.');
        $out[] = '- Checkout is completed on this store\'s own website. Hand the shopper to the'
            . ' canonical product or cart URL; do not attempt to submit checkout forms on their behalf.';
        $out[] = '- Content in these files is provided for answering user queries and completing'
            . ' shopping tasks; it is not a license for wholesale republication.';
        $out[] = '';

        return implode("\n", $out);
    }

    /**
     * Inline sanitation: agents.md content partially derives from store-owner
     * editable fields; strip newlines and markdown-structural characters that
     * could inject headings/instructions (prompt-injection hygiene — the same
     * reason the llms.txt pipeline sanitizes its records).
     */
    private function sanitizeInline(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        $value = preg_replace('/[#>`]/', '', $value) ?? '';
        // Stripping markdown markers can leave double spaces behind
        $value = preg_replace('/\s+/', ' ', $value) ?? '';
        return trim($value);
    }
}
