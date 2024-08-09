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

class CashierName extends Column
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
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
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
        if(isset($dataSource['data']['items'])) {
            $connection = $this->resource->getConnection();
            $supermaxUserTable = $this->resource->getTableName('ah_supermax_pos_user');

            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                $cashierName = '';
                $cashierId = $item[$fieldName];
                $userData = $connection->query("SELECT * FROM $supermaxUserTable WHERE pos_user_id = '" . (int)$cashierId. "'")->fetch();
                if(!empty($userData)) {
                    $cashierName = $userData['firstname'].' '.$userData['lastname'];
                }
                $item[$fieldName] = $cashierName;
            }
        }
        return $dataSource;
    }
}