<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;

class Headreconcilesummary extends \Magento\Backend\App\Action
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     *
     */

    public function __construct(
        Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_coreRegistry = $registry;
        parent::__construct($context);
        $this->helper = $helper;
    }

    public function execute()
    {
        $assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();		
		if($assignedOutletIds != -1) {
            $id = $this->getRequest()->getParam('pos_register_id');
            $model = $this->_objectManager->create(\Anyhow\SupermaxPos\Model\SupermaxHeadreconcileSummary::class);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
            $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
            $connection = $resource->getConnection();
            $reportTable = $resource->getTableName('ah_supermax_pos_report');
            $reportData = $connection->query("SELECT * FROM $reportTable Where type ='reconcile' ")->fetchAll();
            $where = $connection->quoteInto('type = ?', 'reconcile');
            $query = $connection->update($reportTable,
                ["pos_register_id" => (int) $id], $where);
            $reportData1 = $connection->query("SELECT * FROM $reportTable Where type ='reconcile' ")->fetchAll();
            
            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->prepend(__('Head Cashier Reconcile Summary'));
            return $resultPage;
        } else{
			// $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
				/** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
				$resultRedirect = $this->resultRedirectFactory->create();
				return $resultRedirect->setPath('supermax/report/nostorereconcile');
		}
        
    }
}
