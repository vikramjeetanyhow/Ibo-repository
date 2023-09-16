<?php
namespace Embitel\Sms\Helper;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Helper\Context;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const OTP_FOR_LOGIN = 'login';
    const OTP_FOR_REGISTRATION = 'registration';
    const XML_PATH_OTP_FORMAT  = 'mobile_otp/settings/otp_format';
    const XML_PATH_OTP_RESEND  = 'mobile_otp/settings/otp_resend';    
    const XML_PATH_OTP_LENGTH  = 'mobile_otp/settings/otp_length';
    const XML_PATH_OTP_MESSAGE = 'mobile_otp/settings/otp_message';
    const XML_PATH_OTP_EXPIRED = 'mobile_otp/settings/otp_expired';
    const XML_PATH_MAGIC_OTP   = 'mobile_otp/settings/master_otp';
    const XML_PATH_IS_PERFORMANCE = 'mobile_otp/settings/enable_performance';
    const XML_PATH_MAGIC_MOBILE_NOS = 'mobile_otp/settings/mobile_numbers';
    const XML_PATH_OTP_INVALID_COUNT = 'mobile_otp/settings/invalid_otp';
    const XML_PATH_OTP_RESEND_BLOCK_TIME    = 'mobile_otp/settings/otp_resend_block_time';
    const XML_PATH_OTP_MAX_RESENDING_TIMES  = 'mobile_otp/settings/otp_max_resending_times';
    
    /**
     * @var array
     */
    protected $otpParameters;

    /**
     * @param Context $context     
     * @param array $otpParameters
     */
    public function __construct(
        Context $context,        
        array $otpParameters = []
    ) {        
        $this->otpParameters = $otpParameters;
        parent::__construct($context);
    }

    /**
     * Get max number of invalid customer OTP verification
     *
     * @param string $storeId
     * @return number
    */
    public function getOtpMaxInvalidTimes($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_OTP_INVALID_COUNT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Otp resend period time
     *
     * @param string $storeId
     * @return number
     */
    public function getOtpResendPeriodTime($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_OTP_RESEND, ScopeInterface::SCOPE_STORE, $storeId);
    }
    
    /**
     * Get max number of times customer resends OTP
     *
     * @param string $storeId
     * @return number
     */
    public function getOtpMaxResendingTimes($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_OTP_MAX_RESENDING_TIMES, ScopeInterface::SCOPE_STORE, $storeId);
    }
    
    /**
     * Get period time of block sending OTP
     *
     * @param string $storeId
     * @return number
     */
    public function getOtpResendBlockTime($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_OTP_RESEND_BLOCK_TIME, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Otp Format
     *
     * @param string $storeId
     * @return string
     */
    public function getOtpFormat($storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OTP_FORMAT, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Charset
     *
     * @return string:
     */
    public function getCharset()
    {
        return str_split($this->otpParameters['charset'][$this->getOtpFormat()]);
    }
    
    /**
     * Get Otp length
     *
     * @param string $storeId
     * @return number
    */
    public function getOtpLength($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_OTP_LENGTH, ScopeInterface::SCOPE_STORE, $storeId);
    }
    
    /**
     * Get Otp expired period time
     *
     * @param string $storeId
     * @return number
    */
    public function getOtpExpiredPeriodTime($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_OTP_EXPIRED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Get Otp Message
     *
     * @param string $storeId
     * @return string
    */
    public function getOtpMessage($storeId = null)
    {
        return $this->scopeConfig->getValue(self::XML_PATH_OTP_MESSAGE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * is Enabled performance testing
     *
     * @param string $storeId
     * @return boolean
    */
    public function isMasterOtpEnabled($storeId = null)
    {
        return $this->scopeConfig
                    ->getValue(self::XML_PATH_IS_PERFORMANCE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getMagicOtp($storeId = null)
    {
        return $this->scopeConfig
                    ->getValue(self::XML_PATH_MAGIC_OTP, ScopeInterface::SCOPE_STORE, $storeId);
    }  

    public function getMasterOtp($mobileNumber, $storeId = null)
    {
        return $this->scopeConfig
                        ->getValue(self::XML_PATH_MAGIC_OTP, ScopeInterface::SCOPE_STORE, $storeId);
        $isEnabled = $this->isMasterOtpEnabled();
        $masterMobileNos = $this->getMobileNumbers();
        if ($isEnabled && in_array($mobileNumber, $masterMobileNos)) {
            $otp = $this->scopeConfig
                        ->getValue(self::XML_PATH_MAGIC_OTP, ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            $otp = '';
        }

         return $otp;
    }

    public function getMobileNumbers($storeId = null)
    {
        $isEnabled = $this->isMasterOtpEnabled();
        $numbers = [];
        if ($isEnabled) {
            $mobileNos = $this->scopeConfig
                        ->getValue(self::XML_PATH_MAGIC_MOBILE_NOS, ScopeInterface::SCOPE_STORE, $storeId);
            if (strpos($mobileNos, ',') !== false) {
                $numbers = explode(',', $mobileNos);
            } else {
                $numbers[] = $mobileNos;
            }
        }

         return $numbers;
    }

    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $this->logger->info($logdata);
        }
    }

    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->scopeConfig->getValue(
                "mobile_otp/settings/otp_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/otp_verification.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
