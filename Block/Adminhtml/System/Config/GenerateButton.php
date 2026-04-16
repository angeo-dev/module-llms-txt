<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateButton extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $url = $this->getUrl('angeo_llms/generate');
        return sprintf(
            '<button type="button" onclick="setLocation(\'%s\')" class="scalable save primary">%s</button>
             <p class="note"><span>%s</span></p>',
            $url,
            __('Generate llms.txt + JSONL'),
            __('Generates files for all active stores. Files are served at yourstore.com/llms.txt')
        );
    }
}
