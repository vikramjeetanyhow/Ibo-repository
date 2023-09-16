<?php
namespace Embitel\Checkout\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;


class UserGroups implements OptionSourceInterface
{ 
    protected $_options; 

    public function __construct(\Magento\Customer\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory)
    {
        $this->_groupCollectionFactory = $groupCollectionFactory;
    }
    
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $this->_options;
        $naOption = array(
            "value" => "",
            "label" => "N/A"
        );
        if (!$this->_options) {
            $this->_options = $this->_groupCollectionFactory->create()->loadData()->toOptionArray();
        }
        array_unshift($this->_options,$naOption);
        return $this->_options;
    }
        
}