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

class Salesdetails extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;
	
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Framework\App\Request\Http $request,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Anyhow\SupermaxPos\Helper\Data $helper
	) {
		parent::__construct($context);
		$this->resource = $resourceConnection;
		$this->request = $request;
		$this->resultPageFactory = $resultPageFactory;
		$this->helper = $helper;
	}

	public function execute()
	{
		$assignedOutletId = array();
        $assignedOutletIds = $this->helper->assignedOutletIds();
		if($assignedOutletIds != -1) {
			$connection = $this->resource->getConnection();
			$reportTable = $this->resource->getTableName('ah_supermax_pos_report');
			$from = $this->request->getParam('date_start');
			$to = $this->request->getParam('date_end');
			$reportData = $connection->query("SELECT * FROM $reportTable Where type ='detail' ")->fetchAll();
			if(!empty($to) && !empty($from)){
				if(empty($reportData)){
					$connection->insert($reportTable,
							['to'=> $to, 'from'=> $from, 'period'=> 'custom', 'type' => 'detail']);       
				}else{
					$where = $connection->quoteInto('type = ?', 'detail');
					$query = $connection->update($reportTable,
								['to'=> $to, 'from'=> $from, 'period'=> 'custom'], $where );
				}
			}
			$resultPage = $this->resultPageFactory->create();
			$resultPage->getConfig()->getTitle()->prepend(__('Sales Report'));
			return $resultPage;
		} else{
			// $this->messageManager->addError(__('you do not have access. Please contact to admin to assign a store.'));
			/** \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
			$resultRedirect = $this->resultRedirectFactory->create();
			return $resultRedirect->setPath('supermax/report/nostoesales');
	}
	}
}