<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\OrderStatus\Block\Adminhtml\Repush;

class Index extends \Magento\Backend\Block\Widget
{
    /**
     * @var string
     */
    protected $_template = 'Embitel_OrderStatus::repush.phtml';

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(\Magento\Backend\Block\Template\Context $context, array $data = [])
    {
        parent::__construct($context, $data);
        $this->setUseContainer(true);
    }
}
