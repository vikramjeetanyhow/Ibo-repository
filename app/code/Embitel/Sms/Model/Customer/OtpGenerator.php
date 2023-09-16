<?php

namespace Embitel\Sms\Model\Customer;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Embitel\Sms\Model\Otp;
use Magento\Customer\Model\Session as CustomerSession;
use Embitel\Sms\Helper\Data as EmbitelSmsHelper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Create customer account resolver
 */
class OtpGenerator
{
    /**
     * @var \Embitel\Sms\Helper\Data
     */
    protected $embitelSmsHelper;

    /**
     * @var \Embitel\Sms\Model\OtpFactory
     */
    protected $otpFactory;

    /**
     *     
     * @param \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory
     * @param \Embitel\Sms\Model\OtpFactory $otpFactory   
     * @param CustomerSession $customerSession
     * @param DateTime $date
     * @param EmbitelSmsHelper $embitelSmsHelper
     * @param \Magento\Email\Model\Template\Filter $filter
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(       
        \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerCollectionFactory,
        \Embitel\Sms\Model\OtpFactory $otpFactory,       
        CustomerSession $customerSession,
        DateTime $date,
        EmbitelSmsHelper $embitelSmsHelper,
        \Magento\Email\Model\Template\Filter $filter,
        StoreManagerInterface $storeManager
    ) {        
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->otpFactory = $otpFactory;        
        $this->customerSession = $customerSession;
        $this->date = $date;
        $this->embitelSmsHelper = $embitelSmsHelper;
        $this->filter = $filter;
        $this->_storeManager = $storeManager;
    }

    public function generateOtpAndSendSms($mobileNumber, $resend, $otpFor)
    {
        try {
            $data = [];
            if($otpFor == EmbitelSmsHelper::OTP_FOR_REGISTRATION){
                $otpFor=2;
            }else if ($otpFor == EmbitelSmsHelper::OTP_FOR_LOGIN){
                $otpFor=1;
            }else{
                $otpFor=0;
            }
            $flag=true;     
            $lastInvalidCount = $this->customerSession->getInvalidOtpCount();
            $lastInvalidOtp = $this->customerSession->getInvalidOtpBlockTime();    
            $storeId = $this->_storeManager->getStore()->getId();
            $invalidCount = $this->embitelSmsHelper->getOtpMaxInvalidTimes($storeId);
            $blockTime = $this->embitelSmsHelper->getOtpResendBlockTime();
            $blockMinutes = $blockTime / 60;             
            $masterOtp = $this->embitelSmsHelper->getMasterOtp($mobileNumber);
            $otpExpired = $this->embitelSmsHelper->getOtpExpiredPeriodTime();
            $expire_date_time = strtotime('+'.$otpExpired.' second', strtotime(date("Y-m-d H:i:s")));  

            if ($lastInvalidOtp != '' && ($lastInvalidCount == '' || ($lastInvalidCount >= $invalidCount))) {
                if (strtotime($this->date->gmtDate()) < (strtotime($lastInvalidOtp) + $blockTime)) {
                    $invalidInterval = abs(strtotime($this->date->gmtDate()) - strtotime($lastInvalidOtp));
                    $invalidMinutes = round($invalidInterval / 60);
                    $invalidDiff = $blockMinutes - $invalidMinutes;
                    $data = [
                        'success' => false,
                        'msg' => __(
                            'You reached max limit of Invalid OTP verification, Please try again after %1 minutes',
                            $invalidDiff
                        ),
                        'can_resend_after' => $invalidDiff
                    ];
                    /* Send otp Message 
                    $message = __(
                        'You are blocked for generating OTP, Please try again after '
                        . $invalidDiff .
                        ' minutes'
                    );
                    $this->embitelSmsHelper->sendSms($mobileNumber, $message);*/
                    return $data;
                }
            }

            if (!$resend) {
                $collection = $this->otpFactory->create()->getCollection()
                        ->addFieldToFilter('customer_mobile_number', $mobileNumber)
                        ->addFieldToFilter('verification_flag', $otpFor)
                        ->setOrder('mobile_id','DESC');
                $mobileCollection = $collection->getFirstItem();
                $expiretimeinterval = strtotime($mobileCollection->getExpireDateTime())-strtotime($this->date->gmtDate());
                
                if ($mobileCollection->getResendCount() == 0) {
                    $interval = abs(strtotime($this->date->gmtDate()) - strtotime($mobileCollection->getLastResend()));
                    $minutes = round($interval / 60);

                    if ($minutes < $blockMinutes) {

                        $difference = $blockMinutes - $minutes;
                        $data = [
                            'success' => false,
                            'msg' => __(
                                'You had reached max limit of resending OTP, Please try again after %1 minutes',
                                $difference
                            ),
                            'can_resend_after' => $difference
                        ];
                        /* Send otp Message 
                        $message = __(
                            'You are blocked for generating OTP, Please try again after '
                                . $difference .
                            ' minutes'
                        );
                        $this->embitelSmsHelper->sendSms($mobileNumber, $message);*/
                        return $data;
                    }else if($expiretimeinterval>0 && $mobileCollection->getVerificationStatus()==1){
                       $otp = $mobileCollection->getOtp();
                       $flag=false;
                    } else {
                        $otp = ($masterOtp == '') ? $this->generateCode()  : $masterOtp;
                    }
                }else if($expiretimeinterval>0 && $mobileCollection->getVerificationStatus()==1){
                       $otp = $mobileCollection->getOtp();
                       $flag=false;
                } else {
                       $otp = ($masterOtp == '') ? $this->generateCode()  : $masterOtp;
                }

                if($flag){
                  $mobile = $this->otpFactory->create();
                    $mobile->addData([                    
                    'customer_mobile_number' => $mobileNumber,
                    'otp' => $otp,
                    'created_date_time' => $this->date->timestamp(),
                    'expire_date_time' => $expire_date_time,
                    'resend_count' => $this->embitelSmsHelper->getOtpMaxResendingTimes(),
                    'last_resend' => '',
                    'verification_flag' => $otpFor                    
                    ])->save();  
                }                

                /* Send otp Message 
                $message = $this->embitelSmsHelper->getOtpMessage();
                $this->filter->setVariables(['otp_code' => $otp]);
                $message = $this->filter->filter($message);
                $this->embitelSmsHelper->sendSms($mobileNumber, $message);
                */
                $resendSeconds = $this->embitelSmsHelper->getOtpResendPeriodTime();
                $data = [
                    'success' => true,
                    'msg' => __('OTP has been sent to your Mobile number'),
                    'can_resend_after' => $resendSeconds                    
                ];
            } else {
                $maxResendCount = $this->embitelSmsHelper->getOtpMaxResendingTimes();
                $resendSeconds = $this->embitelSmsHelper->getOtpResendPeriodTime();
                $collection = $this->otpFactory->create()->getCollection()
                        ->addFieldToFilter('customer_mobile_number', $mobileNumber)
                        ->addFieldToFilter('verification_flag', $otpFor)
                        ->addFieldToFilter('verification_status', Otp::STATUS_NOT_VERIFIED);
                if ($collection->getSize() > 0) {
                    $mobile = $collection->getFirstItem();

                    if ($mobile->getResendCount() == 0) {
                        $blockTime = $this->embitelSmsHelper->getOtpResendBlockTime();
                        $minutes = $blockTime / 60;
                        $data = [
                            'success' => false,
                            'msg' => __(
                                'You have reached max limit of resending OTP, Please try again after '
                                . $minutes . ' minutes'
                            ),
                            'can_resend_after' => $blockTime
                        ];
                    } else {
                        $otp = ($masterOtp == '') ? $this->generateCode()  : $masterOtp;
                        $mobile->addData([
                            'otp' => $otp,
                            'verification_status' => Otp::STATUS_NOT_VERIFIED,
                            'resend_count' => $mobile->getResendCount() - 1,
                            'created_date_time' => $this->date->timestamp(),
                            'expire_date_time' => $expire_date_time,  
                            'last_resend' => $this->date->timestamp(),
                            'verification_flag' => $otpFor
                        ])->save();
                        /*
                        $message = $this->embitelSmsHelper->getOtpMessage();
                        $this->filter->setVariables(['otp_code' => $otp]);
                        $message = $this->filter->filter($message);
                        $this->helper->embitelSmsHelper($mobileNumber, $message);
                        */
                        $data = [
                            'success' => true,
                            'msg' => __('OTP has been sent to your Mobile number'),
                            'can_resend_after' => $resendSeconds
                        ];
                    }                   
                }
            }
        } catch (Exception $ex) {
            $data = [
                'success' => false,
                'msg' => __($ex->getMessage())
            ];
        }
        return $data;
    }

    /**
     * Generate OTP code
     *
     * @return string
    */
    public function generateCode()
    {         
        $charset = $this->embitelSmsHelper->getCharset();    
        $code = '';
        $charsetSize = count($charset);
        $length = max(1, $this->embitelSmsHelper->getOtpLength());
        for ($i = 0; $i < $length; ++$i) {
            $char = $charset[\Magento\Framework\Math\Random::getRandomNumber(0, $charsetSize - 1)];
            $code .= $char;
        }        
        return $code;
    }
}
