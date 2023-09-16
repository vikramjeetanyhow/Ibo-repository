<?php
/**
 * @category   Ibo
 * @package    Ibo_ProductWidget
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\ProductWidget\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class Group implements ArrayInterface
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
        if (!$this->_options) {
            $this->_options = $this->_groupCollectionFactory->create()->loadData()->toOptionArray();
        }
        return $this->_options;
    }
}