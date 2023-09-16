<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model;
use Magento\Framework\DataObject\IdentityInterface;
use Anyhow\SupermaxPos\Api\Data\CashierRoleInterface;

class SupermaxUserRole extends \Magento\Framework\Model\AbstractModel implements CashierRoleInterface, IdentityInterface
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
	
	const CACHE_TAG = 'ah_supermax_pos_user_role';

	protected $_cacheTag = self::CACHE_TAG;
	
    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxUserRole');
    }

    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];
        return $values;
    }
	
	public function getAvailableStatuses()
    {
        return [self::STATUS_ENABLED => __('Enabled'), self::STATUS_DISABLED => __('Disabled')];
    }

    public function getId()
	{
		return parent::getData(self::POS_USER_ROLE_ID);
    }
    
    public function getStatus()
	{
		return $this->getData(self::STATUS);
	}

	public function getAccessPermission()
	{
		return $this->getData(self::ACCESS_PERMISSION);
	}
	
    public function setId($id)
	{
		return $this->setData(self::POS_USER_ROLE_ID, $id);
	}

    public function setStatus($status)
	{
		return $this->setData(self::STATUS, $status);
    }
    
	public function setAccessPermission($access_permission)
	{
		return $this->setData(self::ACCESS_PERMISSION, $access_permission);
	}
}