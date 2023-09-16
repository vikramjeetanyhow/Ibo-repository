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

interface CashierRoleInterface
{
	const POS_USER_ROLE_ID = 'pos_user_role_id';
    const STATUS = 'status';
    const ACCESS_PERMISSION = 'access_permission';

	public function getId();

    public function getStatus();
    
    public function getAccessPermission();


	public function setId($id);

    public function setStatus($status);
    
    public function setAccessPermission($access_permission);

}