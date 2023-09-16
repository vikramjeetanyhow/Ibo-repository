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

interface CashierInterface
{
	const POS_USER_ID = 'pos_user_id' ;
    const STATUS = 'status';
    const USERNAME = 'username';
	const PASSWORD = 'password';
    const FIRSTNAME = 'firstname';
	const LASTNAME  = 'lastname';
	const EMAIL = 'email';
    const PHONE = 'phone';
	const STORE = 'store_view_id';
	const OUTLET  = 'pos_outlet_id';
	const ROLE = 'pos_user_role_id';

	public function getId();

    public function getStatus();
    
    public function getUsername();

	public function getPassword();

	public function getFirstname();

	public function getLastname();

	public function getEmail();

	public function getPhone();

	public function getStore();

	public function getOutlet();

	public function getRole();


	public function setId($id);

    public function setStatus($status);
    
    public function setUsername($username);

	public function setPassword($password);

	public function setFirstname($firstname);

    public function setLastname($lastname);
    
	public function setEmail($email);

	public function setPhone($phone);

	public function setStore($store);

	public function setOutlet($outlet);

	public function setRole($role);

}