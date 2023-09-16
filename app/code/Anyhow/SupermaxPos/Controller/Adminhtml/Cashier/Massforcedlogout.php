<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Cashier;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\CollectionFactory;

class Massforcedlogout extends \Magento\Backend\App\Action
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
    public function __construct(
        Context $context, 
        Filter $filter, 
        CollectionFactory $collectionFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
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
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::cashier_foreced_logout');
	}

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $collectionSize = $collection->getSize();
        $connection = $this->resource->getConnection();
        $userHistoryTable = $this->resource->getTableName('ah_supermax_pos_user_login_history');
        $supermaxApiTable = $this->resource->getTableName('ah_supermax_pos_api');
        foreach ($collection as $cashier) {
            $cashierId = $cashier->getId();
            $connection->query("UPDATE $userHistoryTable SET status = 0, logout_time = NOW(), is_forced = 1 WHERE pos_user_id = $cashierId And status = 1");
            $connection->query("UPDATE $supermaxApiTable SET expire = NOW() WHERE pos_user_id = $cashierId");
        }

        $this->messageManager->addSuccess(__('A total of %1 record(s) have been forced logged out.', $collectionSize));

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/');
    }
}
