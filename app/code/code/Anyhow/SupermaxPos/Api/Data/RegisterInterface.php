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

interface RegisterInterface
{
	const POS_REGISTER_ID = 'pos_register_id';
    const NAME = 'name';
    const POS_USER_ID = 'pos_user_id';
	const POS_OUTLET_ID = 'pos_outlet_id';
	const STATUS = 'status';
    const CLOSE_NOTE = 'close_note';
	const DATE_OPEN  = 'date_open';
	const DATE_CLOSE = 'date_close';

	public function getId();

    public function getName();
    
    public function getUserId();

	public function getOutletId();

	public function getStatus();

	public function getCloseNote();

	public function getDateOpen();

	public function getDateClose();

	public function setId($id);

    public function setName($name);
    
    public function setUserId($userId);

	public function setOutletId($outletId);

	public function setStatus($status);

	public function setCloseNote($closeNote);

    public function setDateOpen($dateOpen);
    
	public function setDateClose($dateClose);

}