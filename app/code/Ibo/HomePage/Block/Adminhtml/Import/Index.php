<?php
/**
 * Bestdeal Products Import form block
 *
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\HomePage\Block\Adminhtml\Import;

class Index extends \Magento\Backend\Block\Widget
{
    /**
     * @var string
     */
    protected $_template = 'Ibo_HomePage::import.phtml';

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
