<?php

namespace Embitel\Catalog\Block\Adminhtml\Category\Edit\Tab;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CoreMedia extends \Magento\Framework\View\Element\Template
{
    protected $_template = 'catcoremedia.phtml';

    protected $_coreRegistry = null;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    )
    {
        $this->_coreRegistry = $registry;
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
    * @return array|null
    */
    public function getCategory()
    {
        return $this->_coreRegistry->registry('category');
    }

    public function getCoreMediaUrl() {
        return trim($this->_scopeConfig->getValue("core_media/service/get_media_api"));
    }
} 