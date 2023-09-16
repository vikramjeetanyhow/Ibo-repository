<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Outlet;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet\CollectionFactory;

class MassDelete extends \Magento\Backend\App\Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(Context $context, Filter $filter, CollectionFactory $collectionFactory, 
    \Magento\Framework\App\ResourceConnection $resourceConnection)
    {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->resource = $resourceConnection;
        parent::__construct($context);
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::outlet_delete');
	}

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        $connection = $this->resource->getConnection();
        $outletAddressTable = $this->resource->getTableName('ah_supermax_pos_outlet_address');
        $outletProductTable = $this->resource->getTableName('ah_supermax_pos_product_to_outlet');
        $outletCategoryTable = $this->resource->getTableName('ah_supermax_pos_category_to_outlet');

        $collection = $this->filter->getCollection($this->collectionFactory->create());
				
        $collectionSize = $collection->getSize();

        foreach ($collection as $outlet) {
            $outletId = $outlet->getId();
            $connection->query("DELETE FROM $outletAddressTable WHERE parent_outlet_id = $outletId");
            $connection->query("DELETE FROM $outletProductTable WHERE parent_outlet_id = $outletId");
            $connection->query("DELETE FROM $outletCategoryTable WHERE parent_outlet_id = $outletId");
            $outlet->delete();
        }

        $this->messageManager->addSuccess(__('A total of %1 record(s) have been deleted.', $collectionSize));

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
