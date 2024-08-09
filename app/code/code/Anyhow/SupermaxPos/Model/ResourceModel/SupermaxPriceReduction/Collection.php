<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPriceReduction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'pos_price_reduction_id';
	
	protected $_eventPrefix = 'price_reduction_collection';

    protected $_eventObject = 'price_reduction_collection';
   
    protected function _construct()
    {
        $this->_init(
            'Anyhow\SupermaxPos\Model\SupermaxPriceReduction',
            'Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPriceReduction'
        );
    }
}