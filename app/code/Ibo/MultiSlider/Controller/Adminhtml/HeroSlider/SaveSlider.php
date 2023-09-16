<?php

namespace Ibo\MultiSlider\Controller\Adminhtml\HeroSlider;

use Exception;
use Magento\Backend\App\Action;
use Magento\PageCache\Model\Config;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Ibo\MultiSlider\Model\Primary\Model\HeroSliderFactory;
use Ibo\MultiSlider\Model\Primary\ResourceModel\HeroResourceModel;

class SaveSlider extends Action
{
    protected $config;
    protected $serializer;
    protected $typeList;
    protected $resultJsonFactory;
    protected $redirectFactory;
    protected $resourceModel;
    protected $modelFactory;

    public function __construct(
        RedirectFactory $redirectFactory,
        HeroResourceModel $resourceModel,
        HeroSliderFactory $modelFactory,
        SerializerInterface $serializer,
        Context $context,
        Config $config,
        TypeListInterface $typeList,
        JsonFactory $resultJsonFactory
    ) {
        $this->config = $config;
        $this->typeList = $typeList;
        $this->serializer = $serializer;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->redirectFactory = $redirectFactory;
        $this->resourceModel = $resourceModel;
        $this->modelFactory = $modelFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $loadId = $this->getRequest()->getParam('id');
        $modelFactory = $this->modelFactory->create();
        $this->resourceModel->load($modelFactory, $loadId, 'id');
        $formData = $this->getRequest()->getPostValue();
        try {
            $primaryTableData = $this->savePrimaryData($formData);
            //Set all the data in DB from form
            if (!empty($loadId)) {
                $primaryTableData['id'] = $loadId;
            }
            $modelFactory->setData($primaryTableData);
            $this->resourceModel->save($modelFactory);
            $this->messageManager->addSuccessMessage(__("Category Hero slider have been saved !"));
            if ($this->config->isEnabled()) {
                $this->typeList->invalidate(
                    \Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER
                );
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        $resultRedirect = $this->redirectFactory->create();
        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('*/*/edit', ['id' => $modelFactory->getId(), '_current' => true]);
        }
        //Result redirected to index controller
        $resultRedirect = $resultRedirect->setPath('multislider/heroslider/index');
        return $resultRedirect;
    }

    public function savePrimaryData($formData)
    {
        $savePrimary = [
        'identifier' => $formData['identifier'],
        'status' => $formData['status'],
        'type'=>$formData['type']
        ];
        return $savePrimary;
    }
}
