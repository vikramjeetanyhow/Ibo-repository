<?php

/**
 * @version [product Version 1.0.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model;

class StoreId implements \Magento\Framework\Option\ArrayInterface
{
    public function __construct(\Magento\Framework\View\Element\Template\Context $context,
    \Magento\Framework\App\Request\Http $request
    ) {       
        $this->request = $request;    
        $this->context = $context;
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        $result = [];
        $storeId = $this->request->getParam('store');
        if(empty($storeId)){
            $storeId = 0;
        }    
        $result[] = ['value' => $storeId, 'label' => $storeId];
        return $result;
    }
}