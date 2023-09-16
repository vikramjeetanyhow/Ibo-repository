<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Magento\Framework\Exception\NoSuchEntityException;
use Embitel\SalesRule\Model\Rule\Condition\FirstTimePromo;

/**
 * Cms page data provider
 */
class EligiblePromotions
{

    private $ruleFactory;
    private $group;
    private $cart;
    private $productFactory;
    private $customerModel;
    private $customerSegment;
    protected $resourceConnection;
    private $serializerInterface;


    /**
     * @param Magento\SalesRule\Model\RuleFactory $ruleFactory
     * @param Magento\Catalog\Model\ProductFactory $productFactory
     */
    public function __construct(
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\Framework\Serialize\SerializerInterface $serializerInterface,
        //\Magento\CustomerSegment\Model\Customer $customerSegment,
        \Magento\CatalogInventory\Api\StockRegistryInterface $catalogInventory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        FirstTimePromo $firstTimePromo
    ) {
        $this->ruleFactory = $ruleFactory;
        $this->catalogInventory = $catalogInventory;
        $this->request = $request;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        //$this->customerSegment = $customerSegment;
        $this->serializerInterface = $serializerInterface;
        $this->resourceConnection = $resourceConnection;
        $this->productRepository = $productRepository;
        $this->firstTimePromo = $firstTimePromo;
    }

    /**
     * Returns page data by page_id
     *
     * @param int $pageId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getDataByProductId( $productId, $customerId, $customerGroupId): array
    {
        $eligibleData = [];
        $source = [];
        $source[] = 'all';
        $source[] = 'online';

        $filter = [];

        foreach ($source as $value) {
            $filter[] = ['finset' => $value];
        }

        try {
            //Get rules collection by sorting priority and rule_id

            $rules = $this->ruleFactory->create()->getCollection()
                ->addFieldToSelect(['name', 'description', 'sort_order', 'rule_id', 'terms_cond'])
                ->addFieldToFilter("coupon_use_in", [$filter] )
                ->addFieldToFilter("is_active", ['eq' => "1"])
                ->addFieldToFilter("is_show_coupon_pdp", ['eq' => "1"])
                ->addFieldToFilter("coupon_type", ['neq' => \Magento\SalesRule\Model\Rule::COUPON_TYPE_SPECIFIC]);
            // if ($customerId) {
            //     $groupQry = 'select group_id from customer_entity where entity_id=' . $customerId;
            // } else {
            //     $groupQry = 'select customer_group_id from customer_group where customer_group_code="NOT LOGGED IN"';
            // }
            // $connection = $this->resourceConnection->getConnection();
            // $customerGroupId = $connection->fetchOne($groupQry);

            $rules->addCustomerGroupFilter($customerGroupId);
            $rules->getSelect()->order(['sort_order asc', 'rule_id asc']);
            $eligibleRules = [];
            $failedRules = [];
            $rulesArr = [];

            // $parentProduct = $this->productFactory->create()->load($productId);
            $parentProduct = $this->productRepository->getById($productId, false);

            $stockObject = $this->catalogInventory->getStockItem($parentProduct->getId());

            $params = [];
            $params['product'] = $productId;
            $params['qty'] = $stockObject->getMinSaleQty();
            $isSalable = 0;

            if ($parentProduct->getTypeId() == "configurable") {
                $_children = $parentProduct->getTypeInstance()->getUsedProducts($parentProduct);
                if (count($_children) > 0) {
                    foreach ($_children as $child) {
                        $productId = $child->getID();
                        $product = $this->productRepository->getById($productId, false);
                        if ($product->isSaleable()) {
                            //                    Set a highest temporary price for validate rule
                            $isSalable++;
                            break;
                        }
                    }
                    $productAttributeOptions = $parentProduct->getTypeInstance(true)->getConfigurableAttributesAsArray($parentProduct);
                    $options = [];
                    foreach($productAttributeOptions as $option){
                        $options[$option['attribute_id']] =  $product->getData($option['attribute_code']);
                    }
                    $params['super_attribute'] = $options;
                }
            }else{
                if($parentProduct->isSaleable()){
                    $isSalable++;
                }
            }

            if($isSalable > 0 ){
                //Temporary add product to
                // $params['is_promotion'] = 1;
                $request = new \Magento\Framework\DataObject();
                $request->setData($params);
                $cartId = $this->cartManagementInterface->createEmptyCart(); ////create customer quote
                $quote = $this->cartRepositoryInterface->get($cartId);
                $quote->addProduct($parentProduct, $request);
                $quote->collectTotals()->save();
                foreach ($rules as $tmprule) {
                    $rule = $this->ruleFactory->create()->load($tmprule->getRuleId());
                    $dataToEncode = $rule->getConditionsSerialized();
                    $conditions = $this->serializerInterface->unserialize($dataToEncode);
                    $countCondition = count($conditions);
                    $iCnt=0;
                    if ($countCondition >= 7) {
                        foreach ($conditions['conditions'] as $condition) {
                            // if($condition['type'] == "Magento\CustomerSegment\Model\Segment\Condition\Segment") {
                            //     $conditions['conditions'][$iCnt]['type']= "Embitel\SalesRule\Model\Segment\Condition\Segment";
                            // }
                            if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Product\Subselect" && count($conditions['conditions'])>=1){
                                $conditions['conditions'][$iCnt]['type']= "Embitel\SalesRule\Model\Rule\Condition\Product\Subselect";
                                $subConditions = $condition['conditions'];
                                $subCntr=0;
                                // var_dump($subConditions);
                                foreach ($subConditions as $condition) {
                                    if($condition['type'] == "Magento\SalesRule\Model\Rule\Condition\Product"){
                                        if(isset($product)){
                                            $attribute = $product->getResource()->getAttribute($condition['attribute']);

                                        }else{
                                            $attribute = $parentProduct->getResource()->getAttribute($condition['attribute']);
                                        }
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
                                        if(isset($product)){
                                            $attribute = $product->getResource()->getAttribute($condition['attribute']);

                                        }else{
                                            $attribute = $parentProduct->getResource()->getAttribute($condition['attribute']);
                                        }
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
                        $rule->setConditionsSerialized($newConditions);
                        $dataToEncode = $rule->getConditionsSerialized();
                        $conditions = $this->serializerInterface->unserialize($dataToEncode);
                    }

                    //Create a array with rule information to use outside of loop
                    $rulesArr[$rule->getRuleId()]['name'] = $tmprule->getName();
                    $rulesArr[$rule->getRuleId()]['desc'] = $tmprule->getDescription();
                    $rulesArr[$rule->getRuleId()]['terms_cond'] = $tmprule->getTermsCond();
                    $rulesArr[$rule->getRuleId()]['sort_order'] = $tmprule->getSortOrder();

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
                                    if($customerId !=''){
                                        $condValue = explode(",",$condition['value']);
                                        //$customerSegment = $this->customerSegment->getCustomerSegmentIdsForWebsite($customerId, 1);
                                        $matchedSegments= [];//array_intersect($condValue,$customerSegment);
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
                                    if($customerId) {
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
                    $ruleUses = $rule->getUsesPerCustomer();
                    if (($ruleUses) && ($customerId != '')) {
                        $customerUses = 0;
                        $query = "select times_used from salesrule_customer where rule_id='".$rule->getRuleId()."' and customer_id='".$customerId."'";
                        $result = $connection->fetchOne($query);
                        $customerUses = $result;
                        if($customerUses && $customerUses >= $ruleUses) { //Based on uses per customer unset rule
                            unset($eligibleRules[$rule->getRuleId()]);
                            $failedRules[$rule->getRuleId()] = $rule->getRuleId();
                        }
                    }

                }

                //Delete the temporary quote as we validated all rules
                $quote->delete();
                //Delete temporary cart
                $eligibleRules = array_keys($eligibleRules);
                $failedRules = array_keys($failedRules);
                $finalEligibleRules = array_diff($eligibleRules, $failedRules);
                foreach($finalEligibleRules as $key=>$val){
                    if($val !== null){
                        $name = $rulesArr[$val]['name'];
                        $desc = $rulesArr[$val]['desc'];
                        $terms_cond = $rulesArr[$val]['terms_cond'];
                        $order = $rulesArr[$val]['sort_order'];
                        $eligibleData[] = [
                            "id" => $val,
                            "name" => $name,
                            "desc" => $desc,
                            "terms_cond" => $terms_cond,
                            "sort_order" => $order
                        ];
                    }
                }
            }
            $items = $eligibleData;
            return $items;
        }catch(\Exception $e) {
            return $eligibleData;
        }
    }
}
