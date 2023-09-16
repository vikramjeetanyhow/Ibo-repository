<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Integration\Model\ResourceModel\Oauth\Token\CollectionFactory as TokenCollectionFactory;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Integration\Api\CustomerTokenServiceInterface;

/**
 * Customers Revoke Token resolver, used for GraphQL request processing.
 */
class RevokeCustomerToken implements ResolverInterface
{
    /**
     * @var CustomerTokenServiceInterface
     */
    private $customerTokenService;

    /**
     * @param CustomerTokenServiceInterface $customerTokenService
     */
    public function __construct(
        CustomerTokenServiceInterface $customerTokenService,
        TokenCollectionFactory $tokenModelCollectionFactory
    ) {
        $this->customerTokenService = $customerTokenService;
        $this->tokenModelCollectionFactory = $tokenModelCollectionFactory;
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
         /** @var ContextInterface $context */
        if (true === $context->getExtensionAttributes()->getIsCustomer()) {
            $tokenCollection = $this->tokenModelCollectionFactory->create()->addFilterByCustomerId($context->getUserId());
            if ($tokenCollection->getSize() > 0) {
                $this->addLog($context->getUserId());
                $result = $this->customerTokenService->revokeCustomerAccessToken($context->getUserId());
            }
        }

        return ['result' => true];
    }

    public function addLog($logdata){
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ibo_logout.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info("Logout customer Id: ". $logdata);
    }
}
