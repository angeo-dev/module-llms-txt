<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Config\Source;

use Angeo\LlmsTxt\Model\Stock\SalableStatusResolver;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * @since 4.2.0
 */
class StockSource implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => SalableStatusResolver::SOURCE_AUTO,
                'label' => __('Auto — Multi-Source Inventory when installed'),
            ],
            [
                'value' => SalableStatusResolver::SOURCE_LEGACY,
                'label' => __('Legacy stock index'),
            ],
        ];
    }
}
