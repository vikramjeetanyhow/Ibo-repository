<?php

namespace Ibo\MultiSlider\Controller\Adminhtml\HeroSlider;

use Ibo\MultiSlider\Model\Primary\Collection\HeroSliderDataFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;
use Exception;

class MassStatus extends Action
{

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    protected $resultFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        HeroSliderDataFactory $collectionFactory,
        ResultFactory $resultFactory,
        Context $context,
        Filter $filter
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->resultFactory = $resultFactory;
        $this->filter = $filter;
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return Redirect
     * @throws LocalizedException|Exception
     */
    public function execute()
    {
        //Get the status
        $statusValue = $this->getRequest()->getParam('status');
        //Get the Collection
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        // Set status for each item
        foreach ($collection as $item) {
            $item->setStatus($statusValue);
            $item->save();
        }
        //Success message
        $this->messageManager->addSuccessMessage(
            __('A total of %1 record(s) have been modified.', $collection->getSize())
        );

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        //Result redirected to index controller
        return $resultRedirect->setPath('*/*/index');
    }
}
