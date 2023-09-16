<?php
namespace Embitel\Sms\Model\Customer;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Integration\Model\ResourceModel\Oauth\Token as ResourceToken;
use Magento\Integration\Model\Oauth\Token as Token;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;
use Magento\Integration\Model\Oauth\Token\RequestThrottler;
use Magento\Framework\Event\ManagerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;

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
     * Customer Repository
     *
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RequestThrottler
     */
    private $requestThrottler;    

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     *
     * @param TokenModelFactory $tokenModelFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param ManagerInterface $eventManager     
     * @param DateTime $date
     * @param ScopeConfigInterface $scopeConfig
     * @param CollectionFactory $embTokenCollection
     */
    public function __construct(
        TokenModelFactory $tokenModelFactory,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $eventManager = null,        
        DateTime $date, 
        ResourceToken $tokenResource,
        ScopeConfigInterface $scopeConfig        
    ) {
        $this->tokenModelFactory = $tokenModelFactory;
        $this->customerRepository = $customerRepository;        
        $this->scopeConfig = $scopeConfig;
        $this->resourceToken = $tokenResource;
        $this->date = $date;
        
        $this->eventManager = $eventManager ?: \Magento\Framework\App\ObjectManager::getInstance()
            ->get(ManagerInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function createCustomerAccessToken($customer)
    {
        $username = $customer->getEmail();
        $this->getRequestThrottler()->throttle($username, RequestThrottler::USER_TYPE_CUSTOMER);
        $this->eventManager->dispatch('customer_login', ['customer' => $customer]);
        $this->getRequestThrottler()->resetAuthenticationFailuresCount($username, RequestThrottler::USER_TYPE_CUSTOMER); 
        $connection = $this->resourceToken->getConnection();
        $select = $connection->select()
            ->from($this->resourceToken->getMainTable())
            ->where('customer_id = ?', $customer->getId())
            ->where('authorized = ?', 0)
            ->where('revoked = ?', 0)
            ->where('user_type = ?', UserContextInterface::USER_TYPE_CUSTOMER)->order('entity_id DESC');
        $customerTokens = $connection->fetchRow($select);
        if($customerTokens && isset($customerTokens['token'])) { 
            return $customerTokens['token'];
        } else { 
            return $this->tokenModelFactory->create()->createCustomerToken($customer->getId())->getToken();
        }
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
   
}
