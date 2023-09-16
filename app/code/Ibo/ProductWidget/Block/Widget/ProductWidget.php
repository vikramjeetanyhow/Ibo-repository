<?php

namespace Ibo\ProductWidget\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class ProductWidget extends Template implements BlockInterface
{
    protected $_storeManager;

    public function __construct(
        Context $context, 
        StoreManagerInterface $storeManager
      )
    {
 
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }
    
}