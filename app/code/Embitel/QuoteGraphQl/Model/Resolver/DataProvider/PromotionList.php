<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\QuoteGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Embitel\SalesRule\Model\Rule\Condition\FirstTimePromo;

/**
 * Cms page data provider
 */
class PromotionList
{

    private $ruleFactory;
    private $resourceConnection;
    private $group;
    private $serializerInterface;
    private $customerSegment;



    /**
     * @param Magento\SalesRule\Model\RuleFactory $ruleFactory
     */
    public function __construct(
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Serialize\SerializerInterface $serializerInterface,
        //\Magento\CustomerSegment\Model\Customer $customerSegment,
        ProductRepositoryInterface $productRepository,
        \Magento\SalesRule\Model\RuleRepository $ruleRepository,
        \Magento\Customer\Model\Group $group,
        FirstTimePromo $firstTimePromo
    ) {
        $this->ruleFactory = $ruleFactory;
        $this->ruleRepository = $ruleRepository;
        $this->resourceConnection = $resourceConnection;
        $this->productRepository = $productRepository;
        //$this->customerSegment = $customerSegment;
        $this->serializerInterface = $serializerInterface;
        $this->group = $group;
        $this->firstTimePromo = $firstTimePromo;
    }

    /**
     * Returns page data by page_id
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getPromoList($quote,$currentUserId): array
    {
        $eligibleData = array();
        $appliedRulesId = ($quote->getAppliedRuleIds())?explode(',', $quote->getAppliedRuleIds()):null;
        $items = $quote->getAllItems();
        $channel = $quote->getChannel();
        if ($channel === 'store') {
            $channel = $quote->getChannelInfo();
        }
        $source = array('all', $channel);

        $filter = [];

        foreach ($source as $value) {
            $filter[] = ['finset' => $value];
        }

        $connection = $this->resourceConnection->getConnection();

        //Get rules collection by sorting priority and rule_id
        $rules = $this->ruleFactory->create()->getCollection()
                ->addFieldToSelect(array('name','description','sort_order','rule_id', 'terms_cond'))
               ->addFieldToFilter("coupon_use_in", [$filter])
               ->addFieldToFilter("coupon_type",array('neq'=> \Magento\SalesRule\Model\Rule::COUPON_TYPE_SPECIFIC))
                ->addFieldToFilter("is_active",array('eq'=> "1"))
                ->addFieldToFilter("is_show_coupon",array('eq'=> "1"));
                if($currentUserId){
                    $groupQry = 'select group_id from customer_entity where entity_id='.$currentUserId;
                }else{
                    $groupQry = 'select customer_group_id from customer_group where customer_group_code="NOT LOGGED IN"';
                }
                $connection = $this->resourceConnection->getConnection();
                $customerGroupId = $connection->fetchOne($groupQry);
                $rules->addCustomerGroupFilter($customerGroupId);

        $rules->getSelect()->order(array('sort_order asc', 'rule_id asc'));
        $eligibleRules = array();
        $failedRules = array();
        $rulesArr= array();
        $ruleObjArr= array();

        foreach ($items as $item) {
            $productId = $item->getProductId();
            $productObj =  $this->productRepository->getById($productId);
            foreach ($rules as $tmprule) {
                if(isset($ruleObjArr) && isset($ruleObjArr[$tmprule->getRuleId()])) {
                    $rule = $ruleObjArr[$tmprule->getRuleId()];
                } else {
                    $rule = $this->ruleFactory->create()->load($tmprule->getRuleId());
                    $ruleObjArr[$tmprule->getRuleId()] = $rule;
                }
                //Create a array with rule information to use outside of loop
                $rulesArr[$rule->getRuleId()]['name'] = $rule->getName();
                $rulesArr[$rule->getRuleId()]['desc'] = $rule->getDescription();
                $rulesArr[$rule->getRuleId()]['terms_cond'] = $rule->getTermsCond();
                $rulesArr[$rule->getRuleId()]['sort_order'] = $rule->getSortOrder();
    //               $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();

                if(is_array($appliedRulesId) && in_array($tmprule->getRuleId(), $appliedRulesId)){
                     $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                     continue;
                }else{
                    if ($productObj->getTypeId() != "configurable") {
                        $dataToEncode = $rule->getConditionsSerialized();
                        $conditions = (isset($dataToEncode))?$this->serializerInterface->unserialize($dataToEncode):[];
                        $countCondition = count($conditions);
                        $iCnt = 0;
                        if ($countCondition >= 7) {
                        foreach ($conditions['conditions'] as $condition) {
                            if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Product\Subselect" && count($conditions['conditions'])>=1){
                                $conditions['conditions'][$iCnt]['type']= "Embitel\SalesRule\Model\Rule\Condition\Product\Subselect";
                                $subConditions = $condition['conditions'];
                                $subCntr=0;
                                // var_dump($subConditions);
                                foreach ($subConditions as $condition) {
                                    if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Product"){
                                        $attribute = $productObj->getResource()->getAttribute($condition['attribute']);
                                        if(!$attribute) {
                                            //Unset if attribute type is rowtotal
                                            unset($subConditions[$subCntr]);
                                        }
                                    }
                                    $subCntr++;
                                }
                               $conditions['conditions'][$iCnt]['conditions'] = $subConditions;
                            }
                            if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Address"){
                                //Unset the subtotal type of conditions
                                unset($conditions['conditions'][$iCnt]);
                            }
                            if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Product\Found" && count($conditions['conditions'])>=1){
                                $subConditions = $condition['conditions'];
                                $subCntr=0;
                                foreach ($subConditions as $condition) {
                                    if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Product"){
                                        $attribute = $productObj->getResource()->getAttribute($condition['attribute']);
                                        if(!$attribute) {
                                            //Unset if attribute type is rowtotal
                                            unset($subConditions[$subCntr]);
                                        }
                                    }
                                    $subCntr++;
                                }
                               $conditions['conditions'][$iCnt]['conditions'] = $subConditions;
                            }
                            $iCnt++;
                        }

                        $newConditions = $this->serializerInterface->serialize($conditions);
                        // var_dump($newConditions);die;
                        $rule->setConditionsSerialized($newConditions);
                        $dataToEncode = $rule->getConditionsSerialized();
                        $conditions = (isset($dataToEncode))?$this->serializerInterface->unserialize($dataToEncode):[];
                        $countCondition = count($conditions);
                        }
                        // var_dump($conditions);die;
                        if ($countCondition >= 7) {
                            if (isset($conditions['conditions']) && count($conditions['conditions']) >=1 ) {
                                $conditions = $conditions['conditions'];
                                foreach ($conditions as $condition) {
                                    if (isset($condition['conditions']) && count($condition) >= 1) {
                                        $productConditions = $condition['conditions'];
                                        foreach ($productConditions as $productCondition) {
                                            if($productCondition['type'] == 'Magento\SalesRule\Model\Rule\Condition\Product'){
                                                //Validate rule against the temporary quote
                                                $validate = $rule->getConditions()->validate($quote);
                                                if($validate){
                                                    $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                                }else{
                                                    $failedRules[$rule->getRuleId()] = $rule->getRuleId();
                                                }
                                            }
                                            else{
                                                $eligibleRules[$rule->getRuleId()] = $condition['aggregator'];
                                            }
                                        }
                                    }
                                    elseif ( $condition['type'] == "Magento\CustomerSegment\Model\Segment\Condition\Segment") {
                                        if($currentUserId){
                                            $condValue = explode(",",$condition['value']);
                                            $customerSegment = [];//$this->customerSegment->getCustomerSegmentIdsForWebsite($currentUserId, 1);
                                            $matchedSegments= array_intersect($condValue,$customerSegment);
                                            if($condition['operator'] == "==" || $condition['operator'] == "()"){
                                                if(count($matchedSegments)>=1){
                                                    $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                                }else{
                                                     $failedRules[$rule->getRuleId()] = $rule->getRuleId();
                                                }
                                            }else{
                                                if(count($matchedSegments)<=0){
                                                    $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                                }else{
                                                    $failedRules[$rule->getRuleId()] = $rule->getRuleId();
                                                }
                                            }

                                        }else{
                                             $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                        }
                                    }
                                    elseif ( $condition['type'] == "Embitel\SalesRule\Model\Rule\Condition\FirstTimePromo") {
                                        if($currentUserId) {
                                            if($this->firstTimePromo->isFirstTimePromoApplicable()){
                                                $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                            } else {
                                                $failedRules[$rule->getRuleId()] = $rule->getRuleId();
                                            }
                                        }
                                    }
                                    elseif ($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Address") {
                                        $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                    }else{
                                        $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                                    }
                                }
                            }
                            else{
                                $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                            }
                        }
                        else{
                            $eligibleRules[$rule->getRuleId()] = $rule->getRuleId();
                        }

                    }
                }
                $ruleUses = $rule->getUsesPerCustomer();
                if ($ruleUses && $currentUserId) {
                    $customerUses = 0;
                    $query = "select times_used from salesrule_customer where rule_id='".$rule->getRuleId()."' and customer_id='".$currentUserId."'";
                    $result = $connection->fetchOne($query);
                    $customerUses = $result;
                    if($customerUses && $customerUses >= $ruleUses) { //Based on uses per customer unset rule
                        unset($eligibleRules[$rule->getRuleId()]);
                        $failedRules[$rule->getRuleId()] = $rule->getRuleId();
                    }
                }
            }

        }
        // die;
//
        $eligibleRules = array_keys($eligibleRules);
        $failedRules = array_keys($failedRules);
        $finalEligibleRules = array_diff($eligibleRules, $failedRules);
        foreach($finalEligibleRules as $key=>$val){
            if($val !== null){
                $name = $rulesArr[$val]['name'];
                $desc = $rulesArr[$val]['desc'];
                $terms_cond = $rulesArr[$val]['terms_cond'];
                $order = $rulesArr[$val]['sort_order'];
                $is_applied = (is_array($appliedRulesId) && in_array($val, $appliedRulesId))?1:0;
                $eligibleData[] = [
                    "id" => $val,
                    "name" => $name,
                    "desc" => $desc,
                    "terms_cond" => $terms_cond,
                    "sort_order" => $order,
                    "is_applied" => $is_applied
                ];
            }
        }
//        $items = $eligibleData;

        $items = ['items' => $eligibleData];
        return $items;
    }

}
