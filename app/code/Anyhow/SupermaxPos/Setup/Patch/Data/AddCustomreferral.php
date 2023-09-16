<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Anyhow\SupermaxPos\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddCustomreferral implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Framework\App\ResourceConnection $resourceConnection

    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->resource = $resourceConnection;

    }

    public function apply()
    {
        /**
         * Install product link types
         */
        $referral_title = array("Direct Walk-in", "Friends and Family", "Google","IBO Sales","Social Media","Newspaper","Advertisement Banner","Referred by professional");
        $status = 1;
        $connection = $this->resource->getConnection();
        $referralTable = $this->resource->getTableName('ah_supermax_pos_customer_referral');               
        foreach ($referral_title as $bind) {
            $referralData = $connection->query("SELECT * FROM $referralTable Where referral_title ='".$bind."' ")->fetch();
            if(empty($referralData)){           
                $sql = "Insert Into " . $referralTable . "(pos_referral_id,referral_title,status) Values ('','".$bind."',$status)";
                $connection->query($sql);
                
            }
        }    

                
    }

    public function getAliases()
    {
        return [];
    }

    public static function getDependencies()
    {
        return [];
    }
}
