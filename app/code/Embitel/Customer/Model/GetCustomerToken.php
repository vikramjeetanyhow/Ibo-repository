<?php

namespace Embitel\Customer\Model;

use Embitel\Customer\Api\GetCustomerTokenInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\QuoteGraphQl\Model\Cart\CreateEmptyCartForCustomer;

class GetCustomerToken implements GetCustomerTokenInterface
{
    private CreateEmptyCartForCustomer $cartForCustomer;
    private TokenFactory $tokenFactory;
    private ResourceConnection $resourceConnection;

    /**
     * @param CreateEmptyCartForCustomer $cartForCustomer
     * @param TokenFactory $tokenFactory
     */
    public function __construct(TokenFactory $tokenFactory, ResourceConnection $resourceConnection)
    {
        $this->tokenFactory = $tokenFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function getCustomerToken(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $sql = "select token from oauth_token where revoked = 0 AND customer_id=$customerId";
        $token = $connection->fetchOne($sql);

        if (!$token) {
            $customerToken = $this->tokenFactory->create();
            $token = $customerToken->createCustomerToken($customerId)->getToken();
        }

        header("Content-Type: application/json; charset=utf-8");
        $response= json_encode([ 'message' => 'success',
            'token' => $token
        ]);

        print_r($response, false);
        die();
    }
}
