<?php
/**
 * @category   Ibo
 * @package    Ibo_HomePage
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\HomePage\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class HomeBestdeal extends AbstractDb
{
    /**
     * Define main table
     */
    protected function _construct()
    {
        $this->_init('ibo_home_bestdeal', 'id');
    }


    /**
     * Process product data before save
     *
     * @param DataObject $object
     * @return $this
     */
    protected function _beforeSave(\Magento\Framework\Model\AbstractModel $object)
    {
    	$self = parent::_beforeSave($object);
    	$bdcustomerGroup = $object->getData('customer_group');
    	$updateCustomerGroup = $this->getCustomerGroupIds($bdcustomerGroup);    					
		$object->setCustomerGroup($updateCustomerGroup);
    	return $self;
    }

    public function getCustomerGroupIds($custGroup){
	  $customerGroup = "SELECT `main_table`.customer_group_id,`main_table`.customer_group_code FROM `customer_group` AS `main_table`";
	  $customerGroups = $this->getConnection()->fetchAll($customerGroup);
	  $result = '';
	  if($custGroup!=''){
	    $code = array_column($customerGroups, 'customer_group_code');
	    $ids = array_column($customerGroups, 'customer_group_id');
	    $bdGroups = explode(',', $custGroup);
	    $replaceData = str_replace($code,$ids,$bdGroups);
	    $result = implode(',', $replaceData);    
	  }
	  return $result;  
	}
}