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

interface OutletInterface
{
	const POS_OUTLET_ID = 'pos_outlet_id';
	const OUTLET_NAME  = 'outlet_name';
	const SOURCE_CODE = 'source_code';
	const OUTLET_ADDRESS_TYPE = 'outlet_address_type';
    const OUTLET_ADDRESS = 'outlet_address';
	const STATUS = 'status';
	const FIRSTNAME = 'firstname';
	const LASTNAME  = 'lastname';
	const COMPANY = 'company';
    const STREET = 'street';
	const TELEPHONE = 'telephone';
	const POSTCODE  = 'postcode';
	const CITY = 'city';
    const COUNTRY = 'country_id';
	const REGION = 'region';
	const REGIONID = 'region_id';
	const PARENTID = 'parent_outlet_id';

	public function getId();

	public function getOutletName();

	public function getSource();

	public function getOutletAddressType();

	public function getEmail();

	public function getStatus();

	public function getfirstname();

	public function getLastname();

	public function getCompany();

	public function getStreet();

	public function getTelephone();

	public function getCity();

	public function getPostcode();

	public function getCountry();

	public function getRegion();
	
	public function getRegionId();
	
	public function getParentId();

	public function setId($id);

	public function setOutletName($outlet_name);

	public function setSource($source_code);

	public function setOutletAddressType($outlet_address_type);

    public function setEmail($email);
    
	public function setStatus($status);

	public function setfirstname($firstname);

	public function setLastname($lastname);

	public function setCompany($company);

	public function setStreet($street);

	public function setTelephone($telephone);

	public function setCity($city);

	public function setPostcode($postcode);

	public function setCountry($country);

	public function setRegion($region);
	
	public function setRegionId($regionid);
	
	public function setParentId($id);

}