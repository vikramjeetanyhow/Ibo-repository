<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

// All Getters and Setters for every parameters 

namespace Anyhow\SupermaxPos\Api\Supermax\Data;
/**
 * product interface.
 * @api
 */
interface ProductInterface
{

    /**
     * @return int 
     */
    public function getId();

    /**
     * @param int $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return string 
     */
    public function getSku();

    /**
     * @param string $sku
     * @return $this
     */
    public function setSku($sku);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * @return float
     */
    public function getPrice();

    /**
     * @param float $price
     * @return $this
     */
    public function setPrice($price);

    /**
     * @return float
     */
    public function getSpecialPrice();

    /**
     * @param float $specialPrice
     * @return $this
     */
    public function setSpecialPrice($specialPrice);

    /**
     * @return string
     */
    public function getSpecialFromDate();

    /**
     * @param string $specialFromDate
     * @return $this
     */
    public function setSpecialFromDate($specialFromDate);

    /**
     * @return string
     */
    public function getSpecialToDate();

    /**
     * @param string $specialToDate
     * @return $this
     */
    public function setSpecialToDate($specialToDate);

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $productType
     * @return $this
     */
    public function setType($productType);

    /**
     * @return int
     */
    public function getTaxClassId();

    /**
     * @param int $taxClassId
     * @return $this
     */
    public function setTaxClassId($taxClassId);

    /**
     * @return float
     */
    public function getCost();

    /**
     * @param float $cost
     * @return $this
     */
    public function setCost($cost);

    /**
     * @return string[]
     */
    public function getImageUrl();

    /**
     * @param string[] $productImagesArray
     * @return $this
     */
    public function setImageUrl($productImagesArray);

    /**
     * @return string
     */
    public function getBarcode();

    /**
     * @param string $barcode
     * @return $this
     */
    public function setBarcode($barcode);

}