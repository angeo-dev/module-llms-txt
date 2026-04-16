<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Adminhtml\Generate;

use Angeo\LlmsTxt\Model\JsonlGenerator;
use Angeo\LlmsTxt\Model\LlmsGenerator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Angeo_LlmsTxt::config';

    public function __construct(
        Context $context,
        private readonly LlmsGenerator  $llmsGenerator,
        private readonly JsonlGenerator $jsonlGenerator,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $this->llmsGenerator->generate();
            $this->jsonlGenerator->generate();

            $this->messageManager->addSuccessMessage(
                __('llms.txt and JSONL files generated successfully for all active stores.')
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('Generation failed: %1', $e->getMessage())
            );
        }

        return $this->_redirect('adminhtml/system_config/edit/section/angeo_llms');
    }
}
