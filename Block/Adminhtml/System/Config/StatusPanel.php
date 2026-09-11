<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders a live-updating status panel in the system config form that shows
 * per-store / per-format generation status (last success, file size, errors).
 *
 * The panel polls /angeo_llms/status/index once per minute via fetch().
 *
 * @since 3.0.0
 */
class StatusPanel extends Field
{
    /** @var string */
    protected $_template = 'Angeo_LlmsTxt::system/config/status_panel.phtml';

    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->toHtml();
    }

    public function getStatusUrl(): string
    {
        return $this->getUrl('angeo_llms/status/index');
    }

    /**
     * External free-scan URL shown as the panel's next-step CTA.
     *
     * @since 3.3.0
     */
    public function getScanUrl(): string
    {
        return 'https://angeo.dev/scan?utm_source=magento-admin&utm_medium=status-panel';
    }

    /**
     * @since 3.3.0
     */
    public function getSupportEmail(): string
    {
        return 'support@angeo.dev';
    }

    /**
     * Returning '' makes the row span the full width without the standard label.
     */
    protected function _renderScopeLabel(AbstractElement $element): string
    {
        return '';
    }
}
