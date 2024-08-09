<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Outlet\Attribute\Source;

class SourceOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\Setup\SchemaSetupInterface $setup,
        \Magento\Framework\App\ResourceConnection $resourceConnection

    ) {
        $this->setup = $setup;
        $this->moduleManager = $moduleManager;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Retrieve options array.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $result = array();
        $installer = $this->setup;
        // $installer->startSetup();
        if($installer->tableExists('inventory_source')){
            $connection = $this->resourceConnection->getConnection();
            $sourceTableName = $this->resourceConnection->getTableName("inventory_source");
            $sourceData = $connection->query("SELECT * FROM $sourceTableName")->fetchAll();
            if(!empty($sourceData)){
                foreach($sourceData as $source){
                    if($source['enabled'] == 1){
                        $result[] = array(
                            'value' => $source['source_code'], 
                            'label' => $source['name']
                        );
                    }
                }
            } else {
                $result[] = array(
                    'value' => 'default', 
                    'label' => 'Default Source'
                );
            }
        } else {
            $result[] = array(
                'value' => 'default', 
                'label' => 'Default Source'
            );
        }
        // $InventoryModuleStatus = $this->moduleManager->isEnabled('Magento_InventoryApi');
        // if($InventoryModuleStatus){
        //     $sourceCollection = $objectManager->get('Magento\Inventory\Model\ResourceModel\Source\Collection');
        //     $sourceListCollection = $sourceCollection->load();
        //     $sourceData = $sourceListCollection->getData();
        //     if(!empty($sourceData)) {
        //         foreach ($sourceData as $sourceItemName) {
        //             if($sourceItemName['enabled'] == 1){
        //                 $sourceCode = $sourceItemName['source_code'];
        //                 $sourceName = $sourceItemName['name'];
        //                 $result[] = ['value' => $sourceCode, 'label' => $sourceName];
        //             }
        //         }
        //     }
        // }
        // $installer->endSetup();
        return $result;
    }
}