<?php
/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento Cxl Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/

namespace Anyhow\CxlPos\Model;

class Sources implements \Anyhow\CxlPos\Api\SourcesInterface
{
    protected $sourceCollectionFactory;

    public function __construct(
        \Magento\Inventory\Model\ResourceModel\Source\CollectionFactory $sourceCollectionFactory,
        \Anyhow\CxlPos\Helper\Data $helper
    ) {
        $this->sourceCollectionFactory = $sourceCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * Get all sources
     *
     * @return array
    */
    public function getSources() {
        $json = array(
            "error" => false
        );
        try {
            if ($this->helper->getConfig('anyhowcxlpos/general/enable')) {
                $sources = $this->sourceCollectionFactory->create();
                $json['sources'] = [];
                if(!empty($sources)) {
                    foreach ($sources as $source) {
                        $json['sources'][] = array(
                            'source_code' => $source->getSourceCode(),
                            'name' => $source->getName()
                        );
                    }
                }
            } else {
                $json['error'] = true;
            }
        } catch (\Exception $e) {
            $json['error'] = true;
        }  
       
        if($json['error']) {
            $json['message'] = "Something went wrong";
        }

        echo (json_encode($json));
        exit();
    }
}
