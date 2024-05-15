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

class Store implements \Anyhow\CxlPos\Api\StoreInterface
{
    protected $storeManager;
    protected $storeRepository;
    protected $helper;

    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Anyhow\CxlPos\Helper\Data $helper
    ) {
        $this->storeManager = $storeManager;
        $this->storeRepository = $storeRepository;
        $this->helper = $helper;
    }

    public function getStoreList() {
        $json = array(
            "error" => false
        );
        try {
            if ($this->helper->getConfig('anyhowcxlpos/general/enable')) {
                $stores = $this->storeManager->getStores();
                $json['stores'] = [];
                if(!empty($stores)) {
                    foreach ($stores as $store) {
                        $json['stores'][] = array(
                            'store_id' => $store->getId(),
                            'store_code' => $store->getCode(),
                            'website_id' => $store->getWebsiteId(),
                            'group_id' => $store->getGroupId(),
                            'store_name' => $store->getName(),
                            'website_name' => $store->getWebsite()->getName(),
                            'group_name' => $store->getGroup()->getName(),
                            'is_default' => $store->getId() == $this->storeManager->getStore()->getId(),
                            'language' => $store->getConfig('general/locale/code'),
                            'base_currency' => $store->getBaseCurrencyCode(),
                            'store_display_currency' => $store->getCurrentCurrencyCode(),
                            'root_category_id' => $store->getRootCategoryId(),
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
        exit;
    }
}
