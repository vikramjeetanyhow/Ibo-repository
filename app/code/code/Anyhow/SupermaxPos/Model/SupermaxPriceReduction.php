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
use Anyhow\SupermaxPos\Api\Data\PriceReductionInterface;

class SupermaxPriceReduction extends \Magento\Framework\Model\AbstractModel implements PriceReductionInterface, IdentityInterface
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
	
	const CACHE_TAG = 'ah_supermax_pos_price_reductions';

	protected $_cacheTag = self::CACHE_TAG;
	
    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxPriceReduction');
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

    public function getTitle()
	{
		return $this->getData(self::TITLE);
	}
    
    public function getStatus()
	{
		return $this->getData(self::STATUS);
	}

	public function getMaxCapacity()
	{
		return $this->getData(self::MAX_CAPACITY);
	}
	
    public function setId($id)
	{
		return $this->setData(self::POS_PRICE_REDUCTION_ID, $id);
	}

    public function setTitle($title)
	{
		return $this->setData(self::TITLE, $title);
    }

    public function setStatus($status)
	{
		return $this->setData(self::STATUS, $status);
    }
    
	public function setMaxCapacity($max_capacity)
	{
		return $this->setData(self::ACCESS_PERMISSION, $max_capacity);
	}
}