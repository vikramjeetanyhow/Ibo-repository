<?php
/**
 * Top category and brands import form block
 *
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\HomePage\Block\Adminhtml\Topcategory;

class Index extends \Magento\Backend\Block\Widget
{
    /**
     * @var string
     */
    protected $_template = 'Ibo_HomePage::topcategory.phtml';
    
    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->setUseContainer(true);
    }
}
