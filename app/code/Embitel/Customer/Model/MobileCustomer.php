<?php
namespace Embitel\Customer\Model;

use Magento\Integration\Model\Oauth\Token as Token;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Integration\Model\Oauth\Token\RequestThrottler;
use Magento\Framework\Event\ManagerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;

class MobileCustomer
{
    /**
     * Token Model
     *
     * @var TokenModelFactory
     */
    private $tokenModelFactory;

    /**
     * @var Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * @var RequestThrottler
     */
    private $requestThrottler;

    /**
     * Token Model
     *
     * @var TokenModelFactory
     */
    private $embitelTokenFactory;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     *
     * @param TokenModelFactory $tokenModelFactory
     * @param ManagerInterface $eventManager
     * @param CustomerCollectionFactory $customerCollectionFactory
     */
    public function __construct(
        TokenModelFactory $tokenModelFactory,
        ManagerInterface $eventManager = null,
        CustomerCollectionFactory $customerCollectionFactory
    ) {
        $this->tokenModelFactory = $tokenModelFactory;
        $this->eventManager = $eventManager ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(ManagerInterface::class);
        $this->customerCollectionFactory = $customerCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function createCustomerAccessToken($mobileNumber)
    {
        $customer = $this->getCustomerByMobileNumber($mobileNumber);
        if ($customer == '') {
            throw new LocalizedException(__("Entered mobile number doesn't exists"));
        }
        $username = $customer->getEmail();
        $this->getRequestThrottler()->throttle($username, RequestThrottler::USER_TYPE_CUSTOMER);
        $this->eventManager->dispatch('customer_login', ['customer' => $customer]);
        $this->getRequestThrottler()->resetAuthenticationFailuresCount($username, RequestThrottler::USER_TYPE_CUSTOMER);
        return $this->tokenModelFactory->create()->createCustomerToken($customer->getId())->getToken();
    }

    /**
     * Get request throttler instance
     *
     * @return RequestThrottler
     * @deprecated 100.0.4
     */
    private function getRequestThrottler()
    {
        if (!$this->requestThrottler instanceof RequestThrottler) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(RequestThrottler::class);
        }
        return $this->requestThrottler;
    }

    public function getCustomerByMobileNumber($mobileNumber)
    {
        $customer = '';
        $collection = $this->customerCollectionFactory->create()
                   ->addAttributeToFilter('mobilenumber', $mobileNumber);
        if ($collection->getSize() > 0) {
            $customer = $collection->getFirstItem();
            return $customer;
        } else {
            return $customer;
        }
    }
}
