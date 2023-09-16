<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\Cms\Block;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\Template\Context;

/**
 * Get content field of the cms block in the page.
 */
class Block extends \Magento\Framework\View\Element\Template
{
    /**
     * @var BlockFactory 
     */
    protected $blockFactory;

    /**
     * @var FilterProvider 
     */
    protected $filterProvider;

    /**
     * @param Context $context
     * @param BlockFactory $blockFactory
     * @param FilterProvider $filterProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        BlockFactory $blockFactory,
        FilterProvider $filterProvider,
        array $data = array()
    ) {
        $this->blockFactory = $blockFactory;
        $this->filterProvider = $filterProvider;
        parent::__construct($context, $data);
    }

    /**
     * Get CMS static block filtered content.
     */
    public function getBlockData()
    {
        $params = $this->_request->getParams();
        $block = $this->blockFactory->create()->load($params['block_id'], 'identifier');
        if($block->getIsActive()){
            return $this->filterProvider->getBlockFilter()->filter($block->getContent());
        }else{
            return "<b>The page you requested was not found.</b>";
        }
        
    }
}
