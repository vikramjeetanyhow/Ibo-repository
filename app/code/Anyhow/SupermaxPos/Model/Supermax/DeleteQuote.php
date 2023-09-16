<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

use Magento\Framework\DataObject;

class DeleteQuote extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\DeleteQuoteInterface
{    
    protected $coupon;
    protected $saleRule; 

    public function __construct( 
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Api\Data\CartItemInterfaceFactory $cartItem,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\SalesRule\Model\RuleFactory $ruleFactory,
        \Magento\Customer\Model\Group $group
    ){
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->cartItem = $cartItem;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->quoteFactory = $quoteFactory;
        $this->resource = $resourceConnection;
        $this->supermaxSession = $supermaxSession;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->ruleFactory = $ruleFactory;
        $this->group = $group;
    }

    /**
     * GET API
     * @api
     * @return string
     */
    public function deleteQuote() {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(!empty($params)) {
                    if(isset($params['quote_id']) && $params['quote_id']) {
                        $quoteId = $params['quote_id'];
                        if($params['update']) {
                            $this->helper->deactivateQuote($quoteId);
                        } else {
                            $quote = $this->quoteFactory->create()->load($quoteId);
                            $customerId = $quote->getCustomerId();
                            $customerToken = isset($params['customer_token']) ? $params['customer_token'] : "";
                            // $quote->delete();
                            // if(!empty($customerId) && !empty($customerToken)) {
                            //     $this->helper->revokeCustomerToken($customerId, $customerToken);
                            // }
                        }
                    } else {
                        $error = true;
                    }
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }

        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}
