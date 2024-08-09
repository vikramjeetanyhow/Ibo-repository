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

class Inventorysave extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
	
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Backend\Model\View\Result\Redirect $resultRedirect
	) {
		parent::__construct($context);
		$this->resource = $resourceConnection;
		$this->resultRedirect = $resultRedirect;
	}

	public function execute()
	{
		$connection = $this->resource->getConnection();
        $reportTable = $this->resource->getTableName('ah_supermax_pos_report');
		$data = $this->getRequest()->getPostValue();
		// $outlet = !empty($data['outlet']) ? implode(",",$data['outlet']) : ' ';
		$outlet = isset($data['outlet']) ? json_encode($data['outlet']) : '';
		$reportData = $connection->query("SELECT * FROM $reportTable Where type ='inventory' ")->fetchAll();
		// if(!empty($data['period'])){
			if(empty($reportData)){
				$connection->insert($reportTable,
						['to'=> $data['to'], 'from'=> $data['from'], 'period'=> $data['period'], 'filter'=> $data['period1'], 'type' => 'inventory', 'pos_user_id' => $data['cashier'], 'pos_outlet_id' => $outlet]);       
			}else{
				$where = $connection->quoteInto('type = ?', 'inventory');
            	$query = $connection->update($reportTable,
                            ['to'=> $data['to'], 'from'=> $data['from'], 'period'=> $data['period'], 'filter'=> $data['period1'], 'pos_user_id' => $data['cashier'], 'pos_outlet_id' => $outlet], $where );
			}
		// }


		return $this->resultRedirect->setPath('supermax/report/inventory');
	}
}