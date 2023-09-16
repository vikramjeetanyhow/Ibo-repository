<?php

namespace Ibo\DynamicBlockScript\Controller\Adminhtml\BlockScript;

use Ibo\DynamicBlockScript\Model\BlockScriptFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;
use Magento\Framework\Registry;

class Edit extends Action
{

    protected $resultPageFactory;
    protected $coreRegistry;
    protected $model;

    public function __construct(
        PageFactory $resultPageFactory,
        BlockScriptFactory $model,
        Registry $coreRegistry,
        Context $context
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->model = $model;
        parent::__construct($context);
    }

    public function execute()
    {
        $loadId = $this->getRequest()->getParam('id'); 
        $imageData = $this->model->create();
        if ($loadId) {
            //Load the model using id
            $scriptData = $imageData->load($loadId);
            if ($scriptData->getId()) {
                //Register the model in core registry
                $this->coreRegistry->register('blockscript', $scriptData);
            }
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Block Script'));
        return $resultPage;
    }
}
