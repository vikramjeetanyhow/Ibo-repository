<?php
/**
 * Package Dimension Import form block
 *
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Block\Adminhtml\Dimension;

class Index extends \Magento\Backend\Block\Widget
{
    /**
     * @var string
     */
    protected $_template = 'Ibo_ProductUpdate::dimension.phtml';

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
