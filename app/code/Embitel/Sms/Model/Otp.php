<?php

namespace Embitel\Sms\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;

class Otp extends AbstractModel
{
	const STATUS_NOT_VERIFIED   = 1;
    const STATUS_VERIFIED       = 0;

    protected function _construct()
    {
        $this->_init(\Embitel\Sms\Model\ResourceModel\Otp::class);
    }

    /**
     * @return boolean
     */
    public function isVerified()
    {
        return $this->getVerificationStatus() == self::STATUS_VERIFIED;
    } 
}
