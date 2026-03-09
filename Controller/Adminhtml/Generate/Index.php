<?php

declare(strict_types=1);

namespace Angeo\Controller\Adminhtml\Generate;

use Angeo\Model\JsonlGenerator;
use Angeo\Model\LlmsGenerator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use function Angeo\LlmsTxt\Controller\Adminhtml\Generate\__;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Angeo_LlmsTxt::config';

    public function __construct(
        private readonly Context $context,
        private readonly LlmsGenerator $llmsGenerator,
        private readonly JsonlGenerator $jsonlGenerator
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $this->llmsGenerator->generate();
            $this->jsonlGenerator->generate();

            $this->messageManager->addSuccessMessage(
                __('LLMS TXT and JSONL files generated successfully for all stores.')
            );

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('Error during generation: %1', $e->getMessage())
            );
        }

        return $this->_redirect('adminhtml/system_config/edit/section/angeo_llms');
    }
}
