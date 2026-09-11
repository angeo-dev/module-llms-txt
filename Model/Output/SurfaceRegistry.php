<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Resolves which agent-facing surfaces exist for a store: this module's own
 * formats (from config) plus sibling Angeo modules detected softly via the
 * module manager — no hard Composer dependency, links appear only when the
 * capability actually exists.
 *
 * @since 3.4.0
 */
class SurfaceRegistry
{
    private const MODULE_UCP = 'Angeo_Ucp';
    private const MODULE_MCP = 'Angeo_McpServer';

    public function __construct(
        private readonly Config $config,
        private readonly ModuleManager $moduleManager
    ) {
    }

    /**
     * @return array{llms_txt:bool,llms_full_txt:bool,jsonl:bool,md_mirror:bool,agents_md:bool,ucp:bool,mcp:bool}
     */
    public function forStore(StoreInterface $store): array
    {
        return [
            'llms_txt'      => $this->config->isLlmsTxtEnabled($store),
            'llms_full_txt' => $this->config->isLlmsFullTxtEnabled($store),
            'jsonl'         => $this->config->isJsonlEnabled($store),
            'md_mirror'     => $this->config->isMdMirrorEnabled($store),
            'agents_md'     => $this->config->isAgentsMdEnabled($store),
            'ucp'           => $this->moduleManager->isEnabled(self::MODULE_UCP),
            'mcp'           => $this->moduleManager->isEnabled(self::MODULE_MCP),
        ];
    }
}
