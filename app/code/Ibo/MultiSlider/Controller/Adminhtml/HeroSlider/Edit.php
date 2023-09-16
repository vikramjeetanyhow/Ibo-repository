<?php

namespace Ibo\MultiSlider\Controller\Adminhtml\HeroSlider;

use Ibo\MultiSlider\Model\Primary\Model\HeroSliderFactory;
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
        HeroSliderFactory $model,
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
            $imageData = $imageData->load($loadId);
            if ($imageData->getId()) {
                //Register the model in core registry
                $this->coreRegistry->register('hero_slider', $imageData);
            }
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Hero Slider'));
        return $resultPage;
    }
}
