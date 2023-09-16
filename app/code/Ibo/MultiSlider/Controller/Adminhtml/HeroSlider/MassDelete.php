<?php

namespace Ibo\MultiSlider\Controller\Adminhtml\HeroSlider;

use Ibo\MultiSlider\Model\Primary\Collection\HeroSliderDataFactory;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Controller\ResultInterface;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;

class MassDelete extends Action
{

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    protected $messageManager;
    protected $resultFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        HeroSliderDataFactory $collectionFactory,
        ManagerInterface $messageManager,
        ResultFactory $resultFactory,
        Context $context,
        Filter $filter
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->messageManager = $messageManager;
        $this->resultFactory = $resultFactory;
        $this->filter = $filter;
        parent::__construct($context);
    }

    /**
     * Dispatch request
     *
     * @return ResultInterface|ResponseInterface
     * @throws NotFoundException
     */
    public function execute()
    {
   //Get the collection
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();
        foreach ($collection as $item) {
            $item->delete();
        }
        $this->messageManager->addSuccessMessage(__('A total of %1 element(s) have been deleted.', $collectionSize));
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        //Result redirect to the index controller
        return $resultRedirect->setPath('*/*/index');
    }
}
