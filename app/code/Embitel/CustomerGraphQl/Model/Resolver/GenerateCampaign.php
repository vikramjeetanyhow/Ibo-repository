<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CustomerGraphQl\Model\Resolver;

use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\CustomerGraphQl\Model\Customer\ExtractCustomerData;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Customers field resolver, used for GraphQL request processing.
 */
class GenerateCampaign implements ResolverInterface
{
    /**
     * @var GetCustomer
     */
    private $getCustomer;

    /**
     * @var ExtractCustomerData
     */
    private $extractCustomerData;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param GetCustomer $getCustomer
     * @param ExtractCustomerData $extractCustomerData
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        GetCustomer $getCustomer,
        ExtractCustomerData $extractCustomerData,
        ResourceConnection $resourceConnection
    ) {
        $this->getCustomer = $getCustomer;
        $this->extractCustomerData = $extractCustomerData;
        $this->resourceConnection = $resourceConnection;
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
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $customer = $this->getCustomer->execute($context);       
        $query = "SELECT customer_campaign_id,campaign_label FROM `customer_group` WHERE `customer_group_id` = " . $customer->getGroupId();
        $connection = $this->resourceConnection->getConnection();
        $customerGroup = $connection->fetchAll($query);
        $campaignId = !empty($customerGroup[0]['customer_campaign_id']) ? (int)$customerGroup[0]['customer_campaign_id'] : (int)0;
        $campaignLabel = !empty($customerGroup[0]['campaign_label']) ? $customerGroup[0]['campaign_label'] : "";
        return ['campaign_id' => $campaignId,'campaign_label' => $campaignLabel];
    }
}
