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

class EdcConfig implements \Anyhow\SupermaxPos\Api\Supermax\EdcConfigInterface
{
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->resource = $resourceConnection;
    }

    /**
     * GET for Post api
     * @api
     * @param string $nodeId
     * @return object
     */
    public function getEdcConfigData($nodeId) {
        $result = array();
        $error = false;
        try {
            $terminalsData = $this->getTerminalsByNodeId($nodeId);
            if(!empty($terminalsData)) {
                foreach ($terminalsData as $key => $terminal) {
                    $result[$key] = array(
                        "terminal_title" => $terminal['title'],
                        "edc_type" => $terminal['edc_type'],
                    );
                    if (($terminal['edc_type'] == "ezetap-hdfc") || ($terminal['edc_type'] == "ezetap-axis")) {
                        $result[$key]["ezetap_app_key"] = $terminal['ezetap_app_key'];
                        $result[$key]["ezetap_username"] = $terminal['ezetap_username'];
                        $result[$key]["ezetap_device_id"] = $terminal['ezetap_device_id'];
                    }

                    if (($terminal['edc_type'] == "pinelabs-axis") || ($terminal['edc_type'] == "pinelabs-icici")) {
                        $result[$key]["pinelabs_merchant_pos_code"] = $terminal['pinelabs_merchant_pos_code'];
                        $result[$key]["pinelabs_device_id"] = $terminal['pinelabs_device_id'];
                        $result[$key]["pinelabs_allowed_mops"] = $terminal['pinelabs_allowed_mops'];
                    }
                }
            } else {
                $error = "EDC not exist!!";
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        if(!empty($error)) {
            $result[]= array(
                'error' => true,
                'message'   => $error
            );
        }
        return $result;
    }

    private function getTerminalsByNodeId($nodeId) {
        $connection = $this->resource->getConnection();
        $supermaxOutletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $supermaxTerminalTable = $this->resource->getTableName('ah_supermax_pos_terminals');
        return $connection->query("SELECT tt.* FROM $supermaxOutletTable AS ot LEFT JOIN $supermaxTerminalTable AS tt on(ot.`pos_outlet_id`=tt.`pos_outlet_id`) WHERE ot.`store_id`='$nodeId' AND ot.`status`=1 AND tt.`status`=1")->fetchAll();
    }
}



