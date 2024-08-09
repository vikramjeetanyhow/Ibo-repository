<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Api\Data;

interface PincodeMasterInterface
{
	const ENTITY_ID = 'entity_id';
    const PINCODE = 'pincode';
    const STATENAME = 'statename';
    const CITY = 'city';
    const DISTRICTNAME = 'districtname';

	public function getId();
    public function getPincode();
    public function getStatename();
    public function getCity();
    public function getDistrictname();

	public function setId($id);
    public function setPincode($pincode);
    public function setStatename($statename);
    public function setCity($city);
    public function setDistrictname($districtname);
}