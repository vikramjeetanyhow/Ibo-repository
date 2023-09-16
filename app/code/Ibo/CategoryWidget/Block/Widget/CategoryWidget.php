<?php

namespace Ibo\CategoryWidget\Block\Widget;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class CategoryWidget extends Template implements BlockInterface
{
    protected $categoryRepository;
    protected $_categoryCollectionFactory;
    protected $_categoryRepository;
    protected $_storeManager;

    public function __construct(
        Context $context, 
        StoreManagerInterface $storeManager, 
        CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\CategoryRepository $categoryRepository
      )
    {
 
        $this->_storeManager = $storeManager;
        $this->_categoryCollectionFactory = $categoryCollectionFactory;
        $this->_categoryRepository = $categoryRepository;
        parent::__construct($context);
    }
    
}