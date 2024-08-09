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
use Anyhow\SupermaxPos\Api\Data\OutletInterface;

class SupermaxPosOutlet extends \Magento\Framework\Model\AbstractModel implements OutletInterface, IdentityInterface
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
	
	const CACHE_TAG = 'ah_supermax_pos_outlet';

    protected $_cacheTag = self::CACHE_TAG;

    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosOutlet');
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
		return parent::getData(self::POS_OUTLET_ID);
	}

	public function getOutletName()
	{
		return $this->getData(self::OUTLET_NAME);
	}

	public function getSource()
	{
		return $this->getData(self::SOURCE_CODE);
	}

	public function getOutletAddressType()
	{
		return $this->getData(self::OUTLET_ADDRESS_TYPE);
	}

	public function getEmail()
	{
		return $this->getData(self::EMAIL);
	}

	public function getStatus()
	{
		return $this->getData(self::STATUS);
	}

	public function getFirstname()
	{
		return $this->getData(self::FIRSTNAME);
	}

	public function getLastname()
	{
		return $this->getData(self::LASTNAME);
	}

	public function getCompany()
	{
		return $this->getData(self::COMPANY);
	}

	public function getStreet()
	{
		return $this->getData(self::STREET);
	}

	public function getCity()
	{
		return $this->getData(self::CITY);
	}
	
	public function getRegion()
	{
		return $this->getData(self::REGION);
	}

	public function getRegionId()
	{
		return $this->getData(self::REGIONID);
	}

	public function getCountry()
	{
		return $this->getData(self::COUNTRY);
	}

	public function getPostcode()
	{
		return $this->getData(self::POSTCODE);
	}

	public function getTelephone()
	{
		return $this->getData(self::TELEPHONE);
	}

	public function getParentId()
	{
		return $this->getData(self::POS_OUTLET_ID);
	}

    public function setId($id)
	{
		return $this->setData(self::POS_OUTLET_ID, $id);
	}

	public function setOutletName($outlet_name)
	{
		return $this->setData(self::OUTLET_NAME, $outlet_name);
	}

	public function setSource($source_code)
	{
		return $this->setData(self::SOURCE_CODE, $source_code);
	}

	public function setOutletAddressType($outlet_address_type)
	{
		return $this->setData(self::OUTLET_ADDRESS_TYPE, $outlet_address_type);
	}

    public function setEmail($email)
	{
		return $this->setData(self::EMAIL, $email);
	}
	
	public function setStatus($status)
	{
		return $this->setData(self::STATUS, $status);
	}

	public function setFirstname($firstname)
	{
		return $this->setData(self::FIRSTNAME, $firstname);
	}

	public function setLastname($lastname)
	{
		return $this->setData(self::LASTNAME, $lastname);
	}

	public function setCompany($company)
	{
		return $this->setData(self::COMPANY, $company);
	}

	public function setStreet($street)
	{
		return $this->setData(self::STREET, $street);
	}

	public function setCity($city)
	{
		return $this->setData(self::CITY, $city);
	}

	public function setRegion($region)
	{
		return $this->setData(self::REGION, $region);
	}

	public function setRegionId($regionid)
	{
		return $this->setData(self::REGIONID, $regionid);
	}

	public function setCountry($country)
	{
		return $this->setData(self::COUNTRY, $country);
	}

	public function setPostcode($postcode)
	{
		return $this->setData(self::POSTCODE, $postcode);
	}

	public function setTelephone($telephone)
	{
		return $this->setData(self::TELEPHONE, $telephone);
	}

	public function setParentId($id)
	{
		return $this->setData(self::POS_OUTLET_ID, $id);
	}
}