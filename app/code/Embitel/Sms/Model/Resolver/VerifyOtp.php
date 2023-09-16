<?php

namespace Embitel\Sms\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Embitel\Sms\Model\Customer\ValidateOtp;
use Embitel\Sms\Model\Customer\ValidateMobile;
use Embitel\Sms\Model\Customer\MobileCustomer;
use Magento\Quote\Model\QuoteFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * Generate Otp for customer resolver
 */
class VerifyOtp implements ResolverInterface
{

    /**
     * @var \Embitel\Sms\Model\Customer\ValidateOtp
     */
    protected $validateOtp;
    
    /**
     * @var MobileCustomer
     */
    protected $mobileCustomer;
    
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     *
     * @param ValidateOtp $validateOtp
     * @param ValidateMobile $validateMobile
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        ValidateOtp $validateOtp,
        ValidateMobile $validateMobile,
        MobileCustomer $mobileCustomer,
        StoreManagerInterface $storeManager,
        CartRepositoryInterface $quoteRepository,
        QuoteFactory $quoteFactory,
        CustomerRepositoryInterface $customerRepository,
        EventManager $eventManager
    ) {
        $this->validateOtp = $validateOtp;
        $this->validateMobile = $validateMobile;
        $this->mobileCustomer = $mobileCustomer;
        $this->storeManager = $storeManager;
        $this->quoteRepository = $quoteRepository;
        $this->eventManager = $eventManager;
        $this->quoteFactory = $quoteFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        /* check if mobile number is passed in graphql and validate the same */
        if (!isset($args['mobilenumber'])) {
            throw new GraphQlInputException(__('Mobile number value should be specified'));
        }

        if (!isset($args['otp'])) {
            throw new GraphQlInputException(__('Otp value should be specified'));
        }
        
        if (!isset($args['otpfor'])) {
            throw new GraphQlInputException(__('Something went wrong, Please try again'));
        }

        $isValidOtp = $this->validateOtp->isOtpValid($args['mobilenumber'], $args['otp'], $args['otpfor']);
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp======isValidOtp=========>".$isValidOtp['is_valid']);
        if (empty($isValidOtp)) {
            throw new GraphQlAuthorizationException(__('Something went wrong please try again!'));
        }
        
        if (!$isValidOtp['is_valid']) {
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp===IF=! isValidOtp=======>".$isValidOtp['msg']);
            throw new GraphQlAuthorizationException(__($isValidOtp['msg']));
        } else {
            if ($args['otpfor'] == 'checkout') {
                $msgOtp = __('Otp has been Verified');
                return ['is_customer' => '', 'token' => '', 'msg'=> $msgOtp];
            } else {
                $customer = $this->validateMobile->isMobileAssociatedToCustomer($args['mobilenumber']);
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp==else==else=======>".$customer->getId());
                $msg1 = "Your entered mobile number is not registered with us,";
                $msg2 = "Please enter your registered Email Id";
                $msg = $msg1." ".$msg2;
                if ($customer === '') {
                    return ['is_customer' => false,
                            'token' => '',
                            'msg'=> __($msg)
                           ];
                } else {
                    $token = $this->mobileCustomer->createCustomerAccessToken($customer);
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp==else==else=else==token=>".$token);
                    /* merge guest quote to logged in when customer token is generated */
                    if (isset($args['guest_quote_id'])) {
                        $guestToken = $args['guest_quote_id'];

                        $this->eventManager->dispatch('generate_customer_token_after', [
                            'guest_quote_id' => $guestToken,
                            'customer_token' => $token
                        ]);
                    }                    
                    
                    /*** Update Customer Quote StoreId Based on Current Store***/
                    $currentStoreId = $this->storeManager->getStore()->getId();
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp==before try=CustStoreID=$currentStoreId=>");
                    try {
                        $quote = $this->quoteRepository->getActiveForCustomer($customer->getId());
                    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {                        
                        /** @var \Magento\Quote\Model\Quote $quote */
                        $quote = $this->quoteFactory->create();                        
                    }
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp==else==else=else==quoteID=>".$quote->getId());
                    $customerData = $this->customerRepository->getById($customer->getId());
                    $quote->setStoreId($currentStoreId);
                    $quote->setCustomer($customerData);
                    $quote->setCustomerIsGuest(0);
                    $this->quoteRepository->save($quote);  
                    
                    /**Code to add customer details on response for account dashboard */
                    $firstname = $customerData->getFirstname();
                    $lastname = $customerData->getLastname();
                    if($customerData->getCustomAttribute('mobilenumber') != null) {
                        $mobileNumber = $customerData->getCustomAttribute('mobilenumber')->getValue();
                    } else {
                        $mobileNumber = '';
                    }
                    $email = $customerData->getEmail();
                    $profilePicture = '';
                    /*** Update Customer Quote StoreId Based on Current Store***/
        $this->validateOtp->embitelSmsHelper->addLog("<=VerifyOtp==return==token==>".$token);
        $this->validateOtp->embitelSmsHelper->addLog("<=END=========================================>");         
                    return ['is_customer' => true, 'token' => $token,                          
                           'msg'=> 'Otp has been Verified','firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'mobilenumber' => $mobileNumber,'profile_picture' => $profilePicture];
                }
            }
        }
    }
}
