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

class Customerreferralsave extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
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
		$outlet = isset($data['outlet']) ? json_encode($data['outlet']) : '';
		$reportData = $connection->query("SELECT * FROM $reportTable Where type ='customer_referral' ")->fetchAll();
		// if(!empty($data['to']) && !empty($data['from']) && !empty($data['period'])){
			if(empty($reportData)){
				$connection->insert($reportTable,
						['to'=> $data['to'], 'from'=> $data['from'], 'period'=> 'no', 'filter'=> 'no', 'type' => 'customer_referral', 'pos_outlet_id' => $outlet]);       
			}else{
				$where = $connection->quoteInto('type = ?', 'customer_referral');
            	$query = $connection->update($reportTable,
                            ['to'=> $data['to'], 'from'=> $data['from'], 'period'=> 'no','filter'=> 'no', 'pos_outlet_id' => $outlet], $where );
			}
		// }

		return $this->resultRedirect->setPath('supermax/report/customerreferral');
	}
}