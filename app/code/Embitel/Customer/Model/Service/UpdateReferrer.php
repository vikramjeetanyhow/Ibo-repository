<?php
namespace Embitel\Customer\Model\Service;

use Embitel\Customer\Api\UpdateReferrerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use \Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;

class UpdateReferrer implements UpdateReferrerInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $resourceConnection;

    private $connection;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    public function update($customer_id, $referrer_customer_id, $referrer_date)
    {
        $connection = $this->resourceConnection->getConnection();
        $query = "SELECT c.*  FROM customer_entity c "
                 . "WHERE c.mobilenumber = " . $referrer_customer_id;
        $referrerUser = $connection->fetchAll($query);

        $return = ['status' => true,'message' => 'updated sucessfully'];
        if(empty($referrerUser)){
            $return['status'] = false;
            $return['message'] = 'referrer not exists';
        }

        $query = "SELECT c.*  FROM customer_entity c "
                 . "WHERE c.mobilenumber = " . $customer_id;
        $referralCustomer = $connection->fetchAll($query);

        if(!empty($referralCustomer) && !empty($return['status'])){
            try{
                $referrerCid = !empty($referrerUser[0]['entity_id']) ? $referrerUser[0]['entity_id'] : 0;
                $referralCid = !empty($referralCustomer[0]['entity_id']) ? $referralCustomer[0]['entity_id'] : 0;
                $customer = $this->customerRepository->getById($referralCid);
                $customer->setCustomAttribute('referrer_customer_id',$referrerCid);
                $customer->setCustomAttribute('referrer_date',$referrer_date);
                $this->customerRepository->save($customer);
                $this->logger->info('referrer updated - ref  Id - ' . $referrer_customer_id . ' - customer id - ' . $customer_id . ' referred date - ' . $referrer_date);
            }catch(Exception $e){
                $return['message'] = $e->getMessage();
                $this->logger->critical($e->getMessage());
            }
        }else{
            $return['message'] = 'customer not exists';
            $return['status'] = false;
        }

        return [$return];
    }
}