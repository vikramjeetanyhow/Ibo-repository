<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\Cms\Block;

use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\Template\Context;

/**
 * Get content field of the cms block in the page.
 */
class Page extends \Magento\Framework\View\Element\Template
{
    /**
     * @var PageFactory 
     */
    protected $pageFactory;

    /**
     * @var FilterProvider 
     */
    protected $filterProvider;

    /**
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param FilterProvider $filterProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        FilterProvider $filterProvider,
        array $data = array()
    ) {
        $this->pageFactory = $pageFactory;
        $this->filterProvider = $filterProvider;
        parent::__construct($context, $data);
    }

    /**
     * Get CMS static page filtered content.
     */
    public function getBlockData()
    {
        $params = $this->_request->getParams();
        $block = $this->pageFactory->create()->load($params['page_id'], 'identifier');
        if($block->getIsActive()){
            return $this->filterProvider->getBlockFilter()->filter($block->getContent());
        }else{
            return "<b>The page you requested was not found.</b>";
        }
    }
}
