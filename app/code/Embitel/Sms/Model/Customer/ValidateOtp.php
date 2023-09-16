<?php

namespace Embitel\Sms\Model\Customer;

use Embitel\Sms\Model\ResourceModel\Otp\CollectionFactory as OtpCollection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Embitel\Sms\Model\Otp;
use Magento\Customer\Model\Session as CustomerSession;
use Embitel\Sms\Helper\Data as EmbitelSmsHelper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Create customer account resolver
 */
class ValidateOtp
{

    /**
     * @var \Embitel\Sms\Model\ResourceModel\Otp\CollectionFactory
     */
    protected $otpCollection;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $_storeManager;

    /**
     *
     * @param OtpCollection $otpCollection
     * @param DateTime $date    
     * @param CustomerSession $customerSession
     * @param EmbitelSmsHelper $embitelSmsHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        OtpCollection $otpCollection,
        DateTime $date,       
        CustomerSession $customerSession,
        EmbitelSmsHelper $embitelSmsHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->otpCollection = $otpCollection;
        $this->date = $date;       
        $this->customerSession = $customerSession;
        $this->embitelSmsHelper = $embitelSmsHelper;
        $this->_storeManager = $storeManager;
    }

    public function isOtpValid($mobileNumber, $otp, $otpFor)
    {
        /* Check if the  OTP is valid or not */
        $data = [];
        if($otpFor == EmbitelSmsHelper::OTP_FOR_REGISTRATION){
            $otpFor=2;
        }else if ($otpFor == EmbitelSmsHelper::OTP_FOR_LOGIN){
            $otpFor=1;
        }else{
            $otpFor=0;
        }
        $this->embitelSmsHelper->addLog("<=START====mobileNumber============>".$mobileNumber);
        $collection = $this->otpCollection->create()
                ->addFieldToFilter('customer_mobile_number', $mobileNumber)
                ->addFieldToFilter('otp', $otp)
                ->addFieldToFilter('verification_flag', $otpFor)
                ->addFieldToFilter('verification_status', 1)
                ->setOrder('created_date_time', 'DESC');
        
        if ($collection->getSize() > 0) {
            $mobile = $collection->getFirstItem();
            /*if ((strtotime($mobile->getCreatedDateTime()) + $this->embitelSmsHelper->getOtpExpiredPeriodTime()) < $this->date->timestamp()) {
                $data['is_valid'] = false;
                $data['msg'] = __('The OTP code is expired.');
            } else {*/
                $data['is_valid'] = true;
                $mobile->setVerificationStatus(Otp::STATUS_VERIFIED)->save();
                $this->customerSession->setInvalidOtpCount(0);
                $this->customerSession->setInvalidOtpBlockTime(null);
            /*}*/
        $this->embitelSmsHelper->addLog("<=ValidateOtp=If====collection > 0========>".$data['is_valid']);
        } else {
            $lastInvalidCount = $this->customerSession->getInvalidOtpCount();
            $storeId = $this->_storeManager->getStore()->getId();
            $invalidCount = $this->embitelSmsHelper->getOtpMaxInvalidTimes($storeId);
            $blockTime = $this->embitelSmsHelper->getOtpResendBlockTime();
            $minutes = $blockTime / 60;
            if ($lastInvalidCount == '') {
                $remainingCount = $invalidCount - 1;
                $lastInvalidCount = 1;
            } else {
                $remainingCount = $invalidCount - $lastInvalidCount;
            }
            if ($lastInvalidCount >= $invalidCount) {
        $this->embitelSmsHelper->addLog("<=ValidateOtp=====Else==IF==lastInvalidCount=====>".$lastInvalidCount);
                $msg1 = 'You have reached max limit of invalid OTPs,';
                $msg2 = ' Try again after %1 minutes';
                $msg = $msg1.$msg2;
                $data['is_valid'] = false;
                $data['msg'] = __($msg, $minutes);

                $this->customerSession->setInvalidOtpBlockTime($this->date->gmtDate());
                $message = __($msg, $minutes);
                //$this->embitelSmsHelper->sendSms($mobileNumber, $message);
            } else {
                $data['is_valid'] = false;
                $data['msg'] = __('The OTP code is not valid, You are left with %1 tries', $remainingCount);
        $this->embitelSmsHelper->addLog("<=ValidateOtp=====Else==Else==is_valid=====>".$data['is_valid']);
            }
            $this->customerSession->setInvalidOtpCount($lastInvalidCount + 1);
        }
        $this->embitelSmsHelper->addLog("<=ValidateOtp=====return=====>".$data['is_valid']);
        return $data;
    }

    public function isOtpVerified($mobileNumber, $page = null)
    {        
        $collection = $this->otpCollection->create()
            ->addFieldToFilter('customer_mobile_number', $mobileNumber)
            ->addFieldToFilter('verification_flag', $page)
            ->setOrder('created_date_time', 'DESC');

        if ($collection->getSize() > 0) {
            $mobile = $collection->getFirstItem();
            if ($mobile->getVerificationStatus() == Otp::STATUS_VERIFIED) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
