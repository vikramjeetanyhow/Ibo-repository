<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\SalesGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\CustomerGraphQl\Model\Customer\GetCustomer;
use Magento\Framework\App\ResourceConnection;

/**
 * Customers Payment Tokens resolver, used for GraphQL request processing.
 */
class FetchCustomerCouponDetails implements ResolverInterface
{

    /**
     * @var GetCustomer
     */
    private $getCustomer;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @param GetCustomer $getCustomer
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        GetCustomer $getCustomer,
        ResourceConnection $resourceConnection
    ) {
        $this->getCustomer = $getCustomer;
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
        $customerId = null;
        $segmentIds = [];
        $customerGroupId = 0;
        $connection = $this->resourceConnection->getConnection();
        /** @var ContextInterface $context */
        if ($context->getExtensionAttributes()->getIsCustomer()) {
            /*
             //commented for M2 community. No customer Segment
            $customerId = $context->getUserId();
            $customerSegmentTable = $connection->getTableName('magento_customersegment_customer');
            $query = "SELECT segment_id FROM " . $customerSegmentTable . " WHERE `customer_id` = '" . $customerId . "'";
            $getSegmentId = $connection->fetchAll($query);
            $segmentIds = array_column($getSegmentId,'segment_id');
            */
            $customer = $this->getCustomer->execute($context);
            $customerGroupId = $customer->getGroupId();
        }
        $salesRuleTable = $connection->getTableName('salesrule');
        $salesRuleCouponTable = $connection->getTableName('salesrule_coupon');
        $couponTypeId = \Magento\SalesRule\Model\Rule::COUPON_TYPE_SPECIFIC;
        $query = "SELECT `name`,`description`,`sort_order`, `terms_cond`,`conditions_serialized`,src.`code` FROM " . $salesRuleTable . " sr"
            . " INNER JOIN " . $salesRuleCouponTable . " src ON src.rule_id = sr.rule_id"
            . " INNER JOIN salesrule_customer_group srcg ON srcg.rule_id = sr.rule_id"
            . " WHERE `coupon_type` = '" . $couponTypeId . "' AND `is_active` = 1 AND `is_referrer_coupon` = 0 AND `is_show_coupon` = 1 AND srcg.customer_group_id = " . $customerGroupId
            . " ORDER BY sort_order,sr.rule_id ASC";
        $cartRuleInfo = $connection->fetchAll($query);
        $result = [];
        if(!empty($cartRuleInfo)){
            $couponInfo = $connection->fetchAll($query);
            foreach($cartRuleInfo as $cartRule){
                $decodeCouponSerialize = !empty($cartRule['conditions_serialized']) ? json_decode($cartRule['conditions_serialized'],true) : [];
                $cartRule['terms_cond'] = is_null($cartRule['terms_cond']) ? "" : $cartRule['terms_cond'];
                if(!empty($decodeCouponSerialize['conditions'])){

                    /*
                    $key = array_search('Magento\\CustomerSegment\\Model\\Segment\\Condition\\Segment',array_column($decodeCouponSerialize['conditions'],'type'));
                    if($key !== false && !empty($decodeCouponSerialize['conditions'][$key]['value'])){
                        $segmentValue = explode(',',$decodeCouponSerialize['conditions'][$key]['value']);
                        $checkSegment = array_intersect($segmentValue, $segmentIds);
                        if(!empty($checkSegment)){
                           unset($cartRule['conditions_serialized']);
                           $result[] = $cartRule;
                        }
                    }else{

                    */
                        unset($cartRule['conditions_serialized']);
                        $result[] = $cartRule;
                    //}
                }elseif(!empty($cartRule) && is_array($cartRule)){
                    unset($cartRule['conditions_serialized']);
                    $result[] = $cartRule;
                }
            }

        }
        return $result;
    }
}
