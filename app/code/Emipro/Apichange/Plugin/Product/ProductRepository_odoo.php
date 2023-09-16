<?php
namespace Emipro\Apichange\Plugin\Product;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State;
use Magento\Authorization\Model\UserContextInterface;
use Magento\User\Model\UserFactory;

class ProductRepository
{
    private $productRepository;
    private $attribute;
    private $attributecollection;
    private $storeManager;
    private $product;
    private $categoryRepository;
    protected $_scopeConfig;
    private UserContextInterface $userContext;
    private UserFactory $userFactory;

    public function __construct(
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $attributecollection,
        ProductAttributeRepositoryInterface $attributeRepository,
        StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product $product,
        CategoryRepositoryInterface $categoryRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        UserContextInterface $userContext, 
           UserFactory $userFactory,
        State $state
    ) {
        $this->productRepository = $productRepository;
        $this->attribute = $attribute;
        $this->attributecollection = $attributecollection;
        $this->attributeRepository = $attributeRepository;
        $this->storeManager = $storeManager;
        $this->product = $product;
        $this->categoryRepository = $categoryRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->state = $state;
        $this->userContext = $userContext;
           $this->userFactory = $userFactory;
    }

    public function getAdminUser()
    {
           echo $this->userContext->getUserType();
           $userId = $this->userContext->getUserId();
           $user = $this->userFactory->create()->load($userId);
           print_r($user->getData());die;
           return $user;
    }

    public function afterGet(\Magento\Catalog\Model\ProductRepository $subject, $result)
    {
	    $area_code = $this->state->getAreaCode();
        if ($area_code == "webapi_rest"){
            if ($result->getTypeId() == 'configurable' or $result->getTypeId() == 'simple') {
                $extensionAttributes = $result->getExtensionAttributes();
                $extensionAttributes->setWebsiteIds($result->getWebsiteIds());
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
				
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/EMIPROTEST-web.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
				
                $all_website_price = [];
                foreach ($result->getWebsiteIds() as $web) {
                    $website_price = [];
                    $storeId = $this->storeManager->getWebsite($web)->getDefaultStore()->getId();
                    $default_store_currency = $this->storeManager->getStore($storeId)->getDefaultCurrencyCode();
                    $product = $this->product->setStoreId($storeId)->load($result->getId());
                    $pro_final_price = $product->getFinalPrice();
                    $website_price['website_id'] = $web;
                    $website_price['product_price'] = $pro_final_price;
                    $website_price['default_store_currency'] = $default_store_currency;
                    array_push($all_website_price, $website_price);
                    
					$logger->info('DEFAULT_CURRENCY - Start');
					//$logger->info(print_r($DEFAULT_CURRENCY, true));
					$logger->info('DEFAULT_CURRENCY - End');

                }
                $extensionAttributes->setWebsiteWiseProductPriceData($all_website_price);
                $logger->info($this->getAdminUser().'<<--getAdminUser');
                /*Add 'subclass' only to category_ids array.*/
                $categoryIdsObj = $result->getCustomAttribute('category_ids');
                if ($categoryIdsObj) {
                    if ($categoryIdsObj->getValue()) {
                        $categoryIds = $this->getCategoryId($categoryIdsObj->getValue());
                        $result->setCustomAttribute('category_ids', $categoryIds);
                    }
                }

                /*Add simple product's sku and product id*/
                $simple_product = [];
                $configurable_product_options = [];
                $all_opt_data = [];
                if ($result->getTypeId() == 'configurable') {
                    $ConfigurableProductLinks = $extensionAttributes->getConfigurableProductLinks();
                    if (count($ConfigurableProductLinks) > 0) {
                        foreach ($ConfigurableProductLinks as $key => $value) {
                            $product = $this->productRepository->getById($value);
                            $product_data = [];
                            $product_data['simple_product_id'] = $value;
                            $product_data['simple_product_sku'] = $product->getSku();
                            $product_data['simple_product_list_price'] = $product->getPrice() ? $product->getPrice() : 0;
                            /*$product = $this->productRepository->getById($value);*/
                            $product_data['simple_product_attribute'] = [];
                            $_attributes = $result->getTypeInstance(true)->getConfigurableAttributes($result);
                            $simple_product_att = [];
                            foreach ($_attributes as $_attribute) {
                                $attributesPair = [];
                                $attributeId = (int) $_attribute->getAttributeId();
                                $attributeCode = $this->attributeRepository->get($attributeId);
                                if ($product->getCustomAttribute($attributeCode->getAttributeCode())) {
                                    $att_value = $product->getCustomAttribute($attributeCode->getAttributeCode())->getValue();
                                    $attr = $product->getResource()->getAttribute($attributeCode->getAttributeCode());
                                    if ($attr->usesSource()) {
                                        $optionText = $attr->getSource()->getOptionText($att_value);
                                        $attributesPair['label'] = $attributeCode->getFrontendLabel();
                                        $attributesPair['value'] = $optionText;
                                        
										$logger->info('optionText - Start');
										$logger->info(print_r($optionText, true));
										$logger->info(print_r($simple_product_att, true));
										$logger->info(print_r($attributesPair, true));
										$logger->info('optionText - End');
										
                                        array_push($simple_product_att, $attributesPair);
                                    }
                                }
                            }
                            $product_data['simple_product_attribute'] = $simple_product_att;
                            if ($product_data['simple_product_attribute']) {
                                
								$logger->info('simple_product_att - Start');
								$logger->info(print_r($simple_product_att, true));
								$logger->info(print_r($simple_product, true));
								$logger->info(print_r($product_data, true));
								$logger->info('simple_product_att - End');
								
                                array_push($simple_product, $product_data);
                            }
                        }
                    }

                    $ConfigurableProductOptions = $extensionAttributes->getConfigurableProductOptions();
                    if (count($ConfigurableProductOptions) > 0) {
                        foreach ($ConfigurableProductOptions as $key => $ProductOptions) {
                            $product_opt_data = [];
                            $product_opt_data['attribute_id'] = $ProductOptions->getAttributeId();
                            $attr = $this->attribute->load($ProductOptions->getAttributeId());
                            $product_opt_data['frontend_label'] = $attr->getFrontendLabel();
                            $product_opt_data['attribute_code'] = $attr->getAttributeCode();
                            $attribute = $result->getResource()->getAttribute($attr->getAttributeCode());
                            $product_option_value = [];
                            foreach ($ProductOptions->getValues() as $OptionValue) {
                                $optionId = $OptionValue->getValueIndex();
                                $attData = $result->getResource()->getAttribute($attr->getAttributeCode());
                                if ($attData->usesSource()) {
                                    $optionText = $attData->getSource()->getOptionText($optionId);
                                    if ($optionText) {
                                        array_push($product_option_value, $optionText);
                                    }
                                }
                            }
                            $product_opt_data['opt_values'] = $product_option_value;
                            
							$logger->info('product_opt_data - Start');
							$logger->info(print_r($product_opt_data, true));
							$logger->info(print_r($all_opt_data, true));
							$logger->info('product_opt_data - End');
							
                            array_push($all_opt_data, $product_opt_data);
                        }
                    }
                }
                $extensionAttributes->setConfigurableProductOptionsData($all_opt_data);
                $extensionAttributes->setConfigurableProductLinkData($simple_product);
                $result->setExtensionAttributes($extensionAttributes);
            }
        }
        return $result;
    }

    /**
     * Get subclass value of product category.
     *
     * @param type $categoryIds
     * @return type
     */
    private function getCategoryId($categoryIds)
    {
        rsort($categoryIds);
        foreach ($categoryIds as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            //If category doesn't have children then it's 'subclass'
            if (!$category->getChildren()) {
                $path = explode("/", $category->getPath());
                $rootCategory = $this->categoryRepository->get($path[1]);
                if ("Merchandising Category" == $rootCategory->getName()) {
					$categoryEBOID = $category->getCategoryId();
                    return [$categoryEBOID];
                }
            }
        }
        //If there are no categories without children then return last node of original array.
        return (isset($categoryIds[0])) ? [$categoryIds[0]] : [];
    }

    public function afterGetList(
        \Magento\Catalog\Api\ProductRepositoryInterface $subject,
        $products
    ) {
        $area_code = $this->state->getAreaCode();
        if ($area_code == "webapi_rest"){
            foreach ($products->getItems() as $key => $product) {
                $this->afterGet($subject, $product);
            }
        }
        return $products;
    }
}
