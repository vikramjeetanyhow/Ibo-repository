<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Register;

class Update  extends \Magento\Backend\App\Action
{
    protected $jsonFactory;

    public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
		parent::__construct($context);
		$this->resource = $resourceConnection;
        $this->jsonFactory = $jsonFactory;
        $this->helper = $helper;
    }

    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];
		$connection= $this->resource->getConnection();
        $registerTable = $this->resource->getTableName('ah_supermax_pos_register'); //Table name
        if ($this->getRequest()->getParam('isAjax')) {
			$postItems = $this->getRequest()->getParam('items', []);
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
				foreach (array_keys($postItems) as $entityId) 
				{
					$where = $connection->quoteInto('pos_register_id = ?', $entityId);
                    try {
                        $headCashierCashTotal = preg_replace('/[^0-9\.]/', "", $postItems[$entityId]['head_cashier_cash_total']);
                        $headCashierCardTotal = preg_replace('/[^0-9\.]/', "", $postItems[$entityId]['head_cashier_card_total']);
                        $headCashierCustomTotal = preg_replace('/[^0-9\.]/', "", $postItems[$entityId]['head_cashier_custom_total']);
                        // $headCashierPodTotal = preg_replace('/[^0-9\.]/', "", $postItems[$entityId]['head_cashier_pod_total']);
                        $headCashierOfflineTotal = preg_replace('/[^0-9\.]/', "", $postItems[$entityId]['head_cashier_offline_total']);
                        $headCashierNote = $postItems[$entityId]['head_cashier_close_note'];
                
						$query = $connection->update($registerTable,
                            ['head_cashier_cash_total'=> $headCashierCashTotal, 'head_cashier_card_total'=> $headCashierCardTotal, 'head_cashier_custom_total'=> $headCashierCustomTotal, 'head_cashier_offline_total'=> $headCashierOfflineTotal, 'head_cashier_close_note'=> $headCashierNote, 'reconciliation_status' => 1], $where);
                    } catch (\Exception $e) {
                        $messages[] = "[Error:]  {$e->getMessage()}";
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }
}