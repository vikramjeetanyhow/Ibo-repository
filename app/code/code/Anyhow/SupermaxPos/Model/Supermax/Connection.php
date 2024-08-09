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

class Connection extends \Magento\Catalog\Model\AbstractModel implements \Anyhow\SupermaxPos\Api\Supermax\ConnectionInterface
{
    protected $helper;
    
    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Module\Manager $moduleManager
    ){
        $this->helper = $helper; 
        $this->resource = $resourceConnection;
        $this->moduleManager = $moduleManager;
    }
    
    /**
     * GET API
     * @api
     * @param string $conn
     * @return string
     */
    public function connection($conn)
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                if (!empty($conn)) {
                    $posConnection = $this->helper->connectionUpdate($conn);

                    $posConnectionId = (int)$posConnection['connection_id'];

                    $connection = $this->resource->getConnection();
                    $connectionTable = $this->resource->getTableName('ah_supermax_pos_connection');
                    $connectionUpdateTable = $this->resource->getTableName('ah_supermax_pos_connection_update');
                    // check Cfd module is enable/disabled
                    $cfdModuleStatus = $this->moduleManager->isEnabled('Anyhow_SupermaxPosCfd');
                    // check Cfd Moduels status field value
                    $supermaxPosCfdStatus = $this->helper->getConfig('ah_supermax_pos_cust_display_configuration/ah_supermax_pos_basic_configutaion/ah_supermax_pos_status');

                    $connectionData = $connection->query("SELECT * FROM $connectionTable");
                    foreach($connectionData as $conn) {
                        $currentDate = date("Y-m-d H:i:s");
                        $connectionDate = $conn['connection_date'];
                        $connectionId = $conn['pos_connection_id'];
                        $connectionCode = $conn['connection_code'];
                        $diff = (int)((strtotime($currentDate) - strtotime($connectionDate)) / (60 * 60 * 24));
                        
                        if($diff > 30) {
                            $connection->query("DELETE FROM $connectionTable WHERE pos_connection_id = $connectionId");
                            $connection->query("DELETE FROM $connectionUpdateTable WHERE pos_connection_id = $connectionId");
                            if($supermaxPosCfdStatus && $cfdModuleStatus){
                                $cfdCdsCodeTable = $this->resource->getTableName('ah_supermax_pos_cds_code');
                                $connection->query("DELETE FROM $cfdCdsCodeTable WHERE connection_code = $connectionCode");
                            }
                        }

                    }

                    $result = array('pos_connection_id' => $posConnectionId);
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }
}