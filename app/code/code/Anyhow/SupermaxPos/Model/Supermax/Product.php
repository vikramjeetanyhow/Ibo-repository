<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

use Magento\Framework\DataObject;

class Product extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\Data\ProductInterface
 {

    /**
     * @return int 
     */
    public function getId()
    {
        return $this->getData('id');
    }

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {  
        return $this->setData('id', $id);
    }

    /**
     * @return string 
     */
    public function getSku()
    {
        return $this->getData('sku');
    }

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku($sku)
    {
        return $this->setData('sku', $sku);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getData('name');
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        return $this->setData('name', $name);
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->getData('price');
    }

    /**
     * @param float $price
     * @return $this
     */
    public function setPrice($price)
    {
        return $this->setData('price', $price);
    }

    /**
     * @return float
     */
    public function getSpecialPrice()
    {
        return $this->getData('specialPrice');
    }

    /**
     * @param float $specialPrice
     * @return $this
     */
    public function setSpecialPrice($specialPrice)
    {
        return $this->setData('specialPrice', $specialPrice);
    }

    /**
     * @return string
     */
    public function getSpecialFromDate()
    {
        return $this->getData('specialFromDate');
    }

    /**
     * @param string $specialFromDate
     * @return $this
     */
    public function setSpecialFromDate($specialFromDate)
    {
        return $this->setData('specialFromDate', $specialFromDate);
    }

    /**
     * @return string
     */
    public function getSpecialToDate()
    {
        return $this->getData('specialToDate');
    }

    /**
     * @param string $specialToDate
     * @return $this
     */
    public function setSpecialToDate($specialToDate)
    {
        return $this->setData('specialToDate', $specialToDate);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->getData('productType');
    }

    /**
     * @param string $productType
     * @return $this
     */
    public function setType($productType)
    {
        return $this->setData('productType', $productType);
    }

    /**
     * @return string[]
     */
    public function getImageUrl()
    {
        return $this->getData('images');
    }

    /**
     * @param string[] $productImagesArray
     * @return $this
     */
    public function setImageUrl($productImagesArray)
    {
        return $this->setData('images', $productImagesArray);
    }

    /**
     * @return string
     */
    public function getBarcode()
    {
        return $this->getData('barcode');
    }

    /**
     * @param string $barcode
     * @return $this
     */
    public function setBarcode($barcode)
    {
        return $this->setData('barcode', $barcode);
    }
    
    
    /**
     * @return int
     */
    public function getTaxClassId()
    {
        return $this->getData('tax_class_id');
    }

    /**
     * @param int $taxClassId
     * @return $this
     */
    public function setTaxClassId($taxClassId)
    {
        return $this->setData('tax_class_id', $taxClassId);
    }

    /**
     * @return float
     */
    public function getCost()
    {
        return $this->getData('cost');
    }

    /**
     * @param float $cost
     * @return $this
     */
    public function setCost($cost)
    {
        return $this->setData('cost', $cost);
    }

 }