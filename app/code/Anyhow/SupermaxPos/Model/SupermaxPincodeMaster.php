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
use Anyhow\SupermaxPos\Api\Data\PincodeMasterInterface;

class SupermaxPincodeMaster extends \Magento\Framework\Model\AbstractModel implements PincodeMasterInterface, IdentityInterface
{
	const CACHE_TAG = 'pincode';

	protected $_cacheTag = self::CACHE_TAG;
	
    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPincodeMaster');
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
	

    public function getId()
	{
		return parent::getData(self::ENTITY_ID);
    }

    public function getPincode()
	{
		return $this->getData(self::PINCODE);
	}

    public function getStatename()
	{
		return $this->getData(self::STATENAME);
	}

    public function getCity()
	{
		return $this->getData(self::CITY);
	}

    public function getDistrictname()
	{
		return $this->getData(self::DISTRICTNAME);
	}
	
    public function setId($id)
	{
		return $this->setData(self::ENTITY_ID, $id);
	}

    public function setPincode($pincode)
	{
		return $this->setData(self::PINCODE, $pincode);
    }

    public function setStatename($statename)
	{
		return $this->setData(self::STATENAME, $statename);
    }

    public function setCity($city)
	{
		return $this->setData(self::CITY, $city);
    }

    public function setDistrictname($districtname)
	{
		return $this->setData(self::DISTRICTNAME, $districtname);
    }
}