<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

/**
 * Renders the "For Agents & Developers" block appended to llms.txt —
 * mirroring the storefront convention Shopify's native llms.txt established:
 * the content index points agents at the interactive surfaces (agents.md,
 * UCP profile, MCP endpoint), turning llms.txt into a discovery hub.
 *
 * Pure logic, unit-testable. Emitted by both pipelines right before the
 * attribution signature, only for the llms.txt format, only when at least
 * one surface exists.
 *
 * @since 3.4.0
 */
class AgentSurfacesBlock
{
    /**
     * @param array{agents_md?:bool,ucp?:bool,mcp?:bool} $surfaces
     */
    public function render(string $baseUrl, array $surfaces): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $lines = [];

        if (!empty($surfaces['agents_md'])) {
            $lines[] = sprintf('- [Agent guide](%s/agents.md): interaction rules and machine-readable surfaces', $baseUrl);
        }
        if (!empty($surfaces['ucp'])) {
            $lines[] = sprintf('- [UCP profile](%s/.well-known/ucp): commerce capability discovery (Universal Commerce Protocol)', $baseUrl);
        }
        if (!empty($surfaces['mcp'])) {
            $lines[] = sprintf('- [MCP endpoint](%s/mcp): live catalog access over Model Context Protocol (POST, JSON-RPC 2.0)', $baseUrl);
        }

        if ($lines === []) {
            return '';
        }

        return "\n## For Agents & Developers\n\n" . implode("\n", $lines) . "\n";
    }
}
