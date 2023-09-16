<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class OrderCommentDeclared extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\CurrencyInterface $currency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->_storeManager = $storeManager;
        $this->currency = $currency;
        $this->resource = $resourceConnection;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
           
            foreach ($dataSource['data']['items'] as &$item) {
                $entity_id = $item['entity_id'];
                $connection = $this->resource->getConnection();
                $fieldName = 'comment';
                $tableName = $this->resource->getTableName('sales_order_status_history');  
                $registerTransactionDetailTable = $this->resource->getTableName('ah_supermax_pos_register_transaction_detail');
                $sql1 = "SELECT * FROM $registerTransactionDetailTable";
                $transactionDetails = $connection->query($sql1)->fetchAll();
                $sql = "SELECT * FROM $tableName WHERE parent_id = '$entity_id'";
                $reportData = $connection->query($sql)->fetchAll();
                foreach($dataSource['data']['items'] as $paymentData) {
                    $payment_method = explode(' ',$paymentData['payment_data'],2);
                    if($payment_method[0] == 'OFFLINE' || $payment_method[0] == 'CASH' ) {
                        foreach($reportData as $comment) {
                            $item[$fieldName] = $comment['comment'];
                        }
                    } else {
                        $item[$fieldName] = '';
                    }
                }
            }
        }
        return $dataSource;
    }
}
