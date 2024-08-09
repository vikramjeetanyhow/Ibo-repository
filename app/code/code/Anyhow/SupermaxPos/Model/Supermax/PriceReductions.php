<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class PriceReductions implements \Anyhow\SupermaxPos\Api\Supermax\PriceReductionsInterface {
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\App\ResourceConnection $resource
    ){
        $this->helper = $helper;
        $this->supermaxSession = $supermaxSession;
        $this->resource = $resource;
    }

    /**
     * GET API
     * @api
     * @return string
     */
 
    public function getPriceReductions() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $connection = $this->resource->getConnection();
                $tableName = $this->resource->getTableName('ah_supermax_pos_price_reductions');
                $priceReductions = $connection->query("SELECT * FROM " . $tableName . " WHERE status=1")->fetchAll();
                $result = $priceReductions;
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}