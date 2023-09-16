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
use Anyhow\SupermaxPos\Api\Data\ReceiptInterface;

class SupermaxReceipt extends \Magento\Framework\Model\AbstractModel implements ReceiptInterface, IdentityInterface
{	
	const CACHE_TAG = 'ah_supermax_pos_receipt';

    protected $_cacheTag = self::CACHE_TAG;

    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxReceipt');
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
		return parent::getData(self::POS_RECEIPT_ID);
    }
    
    public function getTitle()
	{
		return $this->getData(self::TITLE);
	}

	public function getHeaderLogo()
	{
		return $this->getData(self::HEADERLOGO);
	}

	public function getWidth()
	{
		return $this->getData(self::WIDTH);
    }
    
    public function getBarcodeWidth()
	{
		return $this->getData(self::BARCODEWIDTH);
	}

	public function getFontSize()
	{
		return $this->getData(self::FONTSIZE);
	}

	public function getHeaderDetails()
	{
		return $this->getData(self::HEADERDEATILS);
	}

    public function getFooterDetails()
	{
		return $this->getData(self::FOOTERDEATILS);
	}
    
    public function setId($id)
	{
		return $this->setData(self::POS_RECEIPT_ID, $id);
	}

    public function setTitle($title)
	{
		return $this->setData(self::TITLE, $title);
    }
    
	public function setHeaderLogo($headerLogo)
	{
		return $this->setData(self::HEADERLOGO, $headerLogo);
	}

	public function setWidth($width)
	{
		return $this->setData(self::WIDTH, $width);
	}

    public function setBarcodeWidth($barcodeWidth)
	{
		return $this->setData(self::BARCODEWIDTH, $barcodeWidth);
	}

	public function setFontSize($fontSize)
	{
		return $this->setData(self::FONTSIZE, $fontSize);
	}

    public function setHeaderDetails($headerDetails)
	{
		return $this->setData(self::HEADERDETAILS, $headerDetails);
	}
	
	public function setFooterDetails($footerDetails)
	{
		return $this->setData(self::FOOTERDETAILS, $footerDetails);
    }
}