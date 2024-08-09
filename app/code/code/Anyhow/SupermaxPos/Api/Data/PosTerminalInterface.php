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

interface PosTerminalInterface
{
	const POS_PRICE_REDUCTION_ID = 'pos_terminal_id';
    const POS_OUTLET_ID = 'pos_outlet_id';
    const CODE = 'code';
    const TITLE = 'title';
    const EDC_SERIAL_NO = 'edc_serial_no';
    const POS_SERIAL_NO = 'pos_serial_no';
    const EZETAP_USERNAME = 'ezetap_username';
    const EZETAP_DEVICE_ID = 'ezetap_device_id';
    const STATUS = 'status';

	public function getId();

    public function getOutletId();

    public function getCode();

    public function getTitle();

    public function getEdcSerialNo();

    public function getPosSerialNo();
    public function getEzetapDeviceNo();
    public function getEzetapUsername();


    public function getStatus();


	public function setId($id);

    public function setOutletId($outlet_id);

    public function setCode($code);

    public function setTitle($title);

    public function setEdcSerialNo($edc_serial_no);

    public function setPosSerialNo($pos_serial_no);

    public function setEzetapDeviceNo($ezetap_device_no);  

    public function setEzetapUsername($ezetap_username);

    public function setStatus($status);

}