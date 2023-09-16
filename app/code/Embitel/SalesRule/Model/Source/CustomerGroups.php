<?php
namespace Embitel\SalesRule\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;


class CustomerGroups implements OptionSourceInterface
{
    public function __construct(\Magento\Customer\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory)
    {
        $this->_groupCollectionFactory = $groupCollectionFactory;
    }
    
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $customerGroups = $this->_groupCollectionFactory->create()->loadData()->toOptionArray();
        $naOption = array(
            "value" => "",
            "label" => "N/A"
        );
        array_unshift($customerGroups, $naOption);
        return $customerGroups;
    }
        
}