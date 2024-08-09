<?php 

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Config\Source;

class AllEmailTemplates extends \Magento\Framework\App\Helper\AbstractHelper {

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource
    ) {
        $this->resource = $resource;
    }

    public function toOptionArray() {
        $result = array();
        $result[] = array(
            'label' => '--- None ---',
            'value' => 0
        );
        $connection = $this->resource->getConnection();
        $emailTemplateTable = $this->resource->getTableName('email_template');
        $emailTemplateData = $connection->query("SELECT * FROM $emailTemplateTable");
        if(!empty($emailTemplateData)){
            foreach($emailTemplateData as $emailTemplate){
                $result[] = array(
                    'label' => $emailTemplate['template_code'],
                    'value' => $emailTemplate['template_id']
                );
            }
        }
        return $result;
    }
}