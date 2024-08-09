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
use Anyhow\SupermaxPos\Api\Data\CashierInterface;

class SupermaxTerminal extends \Magento\Framework\Model\AbstractModel implements CashierInterface, IdentityInterface
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
	
	const CACHE_TAG = 'ah_supermax_pos_user_login_history';

	protected $_cacheTag = self::CACHE_TAG;
	
    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxTerminal');
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
		return parent::getData(self::POS_USER_ID);
    }
    
    public function getStatus()
	{
		return $this->getData(self::STATUS);
	}

	public function getUsername()
	{
		return $this->getData(self::USERNAME);
	}

	public function getPassword()
	{
		return $this->getData(self::PASSWORD);
    }
    
    public function getFirstname()
	{
		return $this->getData(self::FIRSTNAME);
	}

	public function getLastname()
	{
		return $this->getData(self::LASTNAME);
	}

	public function getEmail()
	{
		return $this->getData(self::EMAIL);
	}

    public function getPhone()
	{
		return $this->getData(self::PHONE);
	}

	public function getStore()
	{
		return $this->getData(self::STORE);
    }
    
    public function getOutlet()
	{
		return $this->getData(self::OUTLET);
    }

	public function getRole()
	{
		return $this->getData(self::ROLE);
    }
    
    public function setId($id)
	{
		return $this->setData(self::POS_USER_ID, $id);
	}

    public function setStatus($status)
	{
		return $this->setData(self::STATUS, $status);
    }
    
	public function setUsername($username)
	{
		return $this->setData(self::USERNAME, $username);
	}

	public function setPassword($password)
	{
		return $this->setData(self::PASSWORD, $password);
	}

    public function setFirstname($firstname)
	{
		return $this->setData(self::FIRSTNAME, $firstname);
	}

	public function setLastname($lastname)
	{
		return $this->setData(self::LASTNAME, $lastname);
	}

    public function setEmail($email)
	{
		return $this->setData(self::EMAIL, $email);
	}
	
	public function setPhone($phone)
	{
		return $this->setData(self::PHONE, $phone);
    }
	
    public function setStore($store)
	{
		return $this->setData(self::STORE, $store);
    }
    
    public function setOutlet($outlet)
	{
		return $this->setData(self::OUTLET, $outlet);
	}

	public function setRole($role)
	{
		return $this->setData(self::ROLE, $role);
	}
}