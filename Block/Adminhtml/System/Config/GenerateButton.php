<?php

declare(strict_types=1);

namespace Angeo\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class GenerateButton extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->getUrl('angeo_llms/generate');
        return '<button type="button" onclick="setLocation(\''.$url.'\')" class="scalable save">
                    Generate LLMS Files
                </button>';
    }
}
