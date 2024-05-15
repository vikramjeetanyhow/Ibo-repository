<?php
/**
* @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
* @category Anyhow Infosystems
* @package Magento CXL Pos
* @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
* @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
* @license https://store.anyhowinfo.com/software-license
*/

namespace Anyhow\CxlPos\Model;

class Product implements \Anyhow\CxlPos\Api\ProductInterface {
    protected $productCollectionFactory;
    protected $helper;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Anyhow\CxlPos\Helper\Data $helper
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * Get products
     *
     * @return array
    */
    public function getproducts($page_number, $page_size)  {
        $json = array(
            "error" => false
        );
        try {
            if ($this->helper->getConfig('anyhowcxlpos/general/enable')) {
                $products = $this->productCollectionFactory->create();
                $products->setPageSize($page_size);
                $products->setCurPage($page_number);
                $products->addAttributeToSelect('name');
                $json['products'] = [];
                if(!empty($products)) {
                    foreach ($products as $product) {
                        $json['products'][] = array(
                            'id' => (int) $product->getId(),
                            'name' => $product->getName(),
                            'sku' => $product->getSku(),
                            'status' => $product->getStatus(),
                            'catagory_id' => $product->getCategoryIds(),
                        );
                    }
                    $json['total_products'] = sizeof($products);
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
