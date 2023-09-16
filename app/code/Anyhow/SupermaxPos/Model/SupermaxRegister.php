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
use Anyhow\SupermaxPos\Api\Data\RegisterInterface;
class SupermaxRegister extends \Magento\Framework\Model\AbstractModel implements RegisterInterface, IdentityInterface
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
	
	const CACHE_TAG = 'ah_supermax_pos_register';

	protected $_cacheTag = self::CACHE_TAG;
    protected function _construct()
    {
        $this->_init('Anyhow\SupermaxPos\Model\ResourceModel\SupermaxRegister');
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

    public function getName() {
        return $this->getData(self::NAME);
    }
    
    public function getUserId() {
        return $this->getData(self::POS_USER_ID);
    }

	public function getOutletId() {
        return $this->getData(self::POS_OUTLET_ID);
    }

	public function getStatus(){
        return $this->getData(self::STATUS);
    }

	public function getCloseNote() {
        return $this->getData(self::CLOSE_NOTE);
    }

	public function getDateOpen() {
        return $this->getData(self::DATE_OPEN);
    }

	public function getDateClose() {
        return $this->getData(self::DATE_CLOSE);
    }

	public function setId($id) {
        return $this->setData(self::POS_REGISTER_ID, $id);
    }

    public function setName($name) {
        return $this->setData(self::NAME, $name);
    }
    
    public function setUserId($userId) {
        return $this->setData(self::POS_USER_ID, $userId);
    }

	public function setOutletId($outletId) {
        return $this->setData(self::POS_OUTLET_ID, $outletId);
    }

	public function setStatus($status) {
        return $this->setData(self::STATUS, $status);
    }

	public function setCloseNote($closeNote) {
        return $this->setData(self::CLOSE_NOTE, $closeNote);
    }

    public function setDateOpen($dateOpen) {
        return $this->setData(self::DATE_OPEN, $dateOpen);
    }
    
	public function setDateClose($dateClose) {
        return $this->setData(self::DATE_CLOSE, $dateClose);
    }

}

