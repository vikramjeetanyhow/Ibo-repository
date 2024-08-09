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

class GetTerminals implements \Anyhow\SupermaxPos\Api\Supermax\GetTerminalsInterface
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUser\Collection $supermaxUser,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ){
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->supermaxUser = $supermaxUser;
        $this->storeManager = $storeManager;
        $this->supermaxSession = $supermaxSession;
    }

    /**
     * GET for Post api
     * @api
     * @return string
     */
    public function getTerminals()
    {
        $result = array();
        $error = false;
        try {
            $this->helper->setHeaders();
            $connection= $this->resource->getConnection();
            $terminalTable = $this->resource->getTableName("ah_supermax_pos_terminals");
            $storeTable = $this->resource->getTableName("ah_supermax_pos_outlet");
            $terminalResult = $connection->query("SELECT pos_terminal_id, title, pos_outlet_id FROM $terminalTable WHERE status=1")->fetchAll();
            $storeResult = $connection->query("SELECT pos_outlet_id, outlet_name, status FROM $storeTable WHERE status=1")->fetchAll();
            $result = ['terminals'=>$terminalResult, 'stores'=>$storeResult];
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
    	return json_encode($data);
    }
}



