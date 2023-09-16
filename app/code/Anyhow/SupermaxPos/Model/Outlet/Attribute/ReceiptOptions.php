<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Outlet\Attribute;

class ReceiptOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt\Collection $receiptCollection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection


    ){
        $this->receiptCollection = $receiptCollection;
        $this->resource = $resourceConnection;
        $this->helper = $helper;

    }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        $connection = $this->resource->getConnection();
        $SupermaxReceipt = $this->resource->getTableName('ah_supermax_pos_receipt');
        $SupermaxReceiptStore = $this->resource->getTableName('ah_supermax_pos_receipt_store');
        $sql = "SELECT sp.pos_receipt_id,sprs.title FROM $SupermaxReceipt as sp LEFT JOIN $SupermaxReceiptStore as sprs ON(sp.pos_receipt_id = sprs.receipt_id) ";
        // $receiptListCollection = $this->receiptCollection->load();
        // $receiptData = $receiptListCollection->getData();
            $receiptData = $connection->query($sql)->fetchAll();

        if(!empty($receiptData)) {
            foreach ($receiptData as $receiptItemName) {
                $receiptId = $receiptItemName['pos_receipt_id'];
                $receiptName = $receiptItemName['title'];
                $result[] = ['value' => $receiptId, 'label' => $receiptName];
            }
        }
        return $result;
    }
}