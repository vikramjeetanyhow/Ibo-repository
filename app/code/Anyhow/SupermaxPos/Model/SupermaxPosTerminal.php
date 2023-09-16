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
use Anyhow\SupermaxPos\Api\Data\PosTerminalInterface;

class SupermaxPosTerminal extends \Magento\Framework\Model\AbstractModel implements PosTerminalInterface, IdentityInterface
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
	
	const CACHE_TAG = 'ah_supermax_pos_terminals';

	protected $_cacheTag = self::CACHE_TAG;
	
    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPosTerminal');
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
		return parent::getData(self::POS_PRICE_REDUCTION_ID);
    }

    public function getOutletId()
	{
		return $this->getData(self::OUTLET_ID);
	}

    public function getCode()
	{
		return $this->getData(self::CODE);
	}

    public function getTitle()
	{
		return $this->getData(self::TITLE);
	}

    public function getEdcSerialNo()
	{
		return $this->getData(self::EDC_SERIAL_NO);
	}

    public function getEzetapDeviceNo()
    {
        return $this->getData(self::EZETAP_DEVICE_ID);
    }

    public function getEzetapUsername()
    {
        return $this->getData(self::EZETAP_USERNAME);
    }

    public function getPosSerialNo()
	{
		return $this->getData(self::POS_SERIAL_NO);
	}
    
    public function getStatus()
	{
		return $this->getData(self::STATUS);
	}
	
    public function setId($id)
	{
		return $this->setData(self::POS_PRICE_REDUCTION_ID, $id);
	}

    public function setOutletId($outlet_id)
	{
		return $this->setData(self::OUTLET_ID, $outlet_id);
    }

    public function setCode($Code)
	{
		return $this->setData(self::CODE, $Code);
    }

    public function setTitle($title)
	{
		return $this->setData(self::TITLE, $title);
    }

    public function setEdcSerialNo($edc_serial_no)
	{
		return $this->setData(self::EDC_SERIAL_NO, $edc_serial_no);
    }

    public function setEzetapUsername($ezetap_username)
	{
		return $this->setData(self::EZETAP_USERNAME, $ezetap_username);
    }
    public function setEzetapDeviceNo($ezetap_device_no)
	{
		return $this->setData(self::EZETAP_DEVICE_ID, $ezetap_device_no);
    }

    public function setPosSerialNo($pos_serial_no)
	{
		return $this->setData(self::POS_SERIAL_NO, $pos_serial_no);
    }

    public function setStatus($status)
	{
		return $this->setData(self::STATUS, $status);
    }
}