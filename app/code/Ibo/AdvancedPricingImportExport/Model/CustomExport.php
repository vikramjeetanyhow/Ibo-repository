<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\AdvancedPricingImportExport\Model;

use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\Data\TierPriceInterface;
use Magento\Catalog\Api\TierPriceStorageInterface;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Calculation\Rate as TaxCalculationRate;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;

/**
 * Handles the BOM offer ids.
 */
class CustomExport extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{   
    /**
     * @var Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @var TierPriceStorageInterface
     */
    private $tierPrice;

    /**
     * @var CatalogProduct
     */
    private $catalogProduct;

    /**
     * @var TaxCalculation
     */
    protected $taxCalculation;

    /**
     * @var TaxCalculationRate
     */
    protected $taxCalculationRate;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var StockRegistryInterface|null
     */
    private $stockRegistry;
    /**
     * @param ProductFactory $productFactory
     */
    public function __construct(
        LoggerInterface $logger,
        CollectionFactory $_productCollectionFactory,
        TierPriceStorageInterface $tierPrice,
        CatalogProduct $catalogProduct,
        TaxCalculation $taxCalculation,
        TaxCalculationRate $taxCalculationRate,
        ScopeConfigInterface $scopeConfigInterface,
        StockRegistryInterface $stockRegistry

    ) {
        $this->logger = $logger;
        $this->tierPrice = $tierPrice;
        $this->_productCollectionFactory = $_productCollectionFactory;
        $this->catalogProduct = $catalogProduct;
        $this->taxCalculation = $taxCalculation;
        $this->taxCalculationRate = $taxCalculationRate;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->stockRegistry = $stockRegistry;
    }

    /**
     * Retrieve list of BOM offer ids
     *
     * @param mixed $bom_skus
     * @return string
     */
    public function execute()
    {
           /** @var $collection \Magento\Catalog\Model\ResourceModel\Product\Collection */
            $collection = $this->_productCollectionFactory->create();
            $collection->addAttributeToSelect('entity_id');
            $collection->addAttributeToFilter('type_id', 'simple');
            //$collection->getSelect()->limit(10);
            return $collection->getData();
    }

    public function getFilteredData($productRowIds)
    {
        $collection = $this->_productCollectionFactory->create();
       // $collection->addAttributeToSelect('entity_id');
        $collection->addAttributeToSelect(['is_in_stock', 'allowed_channels','mrp', 'department', 'status', 'class', 'subclass', 'is_published', 'meta_title', 'is_lot_controlled', 'lot_control_parameters', 'case_config', 'pack_of', 'tier_price_customer_group', 'tax_class_id', 'price']);
        $collection->addFieldToFilter('entity_id', array('in' => $productRowIds));
        //$collection->setPageSize(5);

        $products = $collection->load();

        $storeScope = ScopeInterface::SCOPE_STORE;
        $isIncludingTax = $this->scopeConfigInterface->getValue(
            'tax/calculation/price_includes_tax',
            $storeScope
        );
        $productData = [];
        $productFinalData = [];
        foreach ($products as $key => $product) {

            $taxCalculationRateValue = 0;
            $productTaxClassId = $product->getData('tax_class_id');
            $taxCalculation = $this->taxCalculation->load($productTaxClassId, 'product_tax_class_id');
            $taxCalculationRateId =  $taxCalculation->getTaxCalculationRateId();
            $taxCalculationRate = $this->taxCalculationRate->load($taxCalculationRateId, 'tax_calculation_rate_id');
            $taxCalculationRateValue =  $taxCalculationRate->getRate();

            if($product->getResource()->getAttribute('pack_of')->getFrontend()->getValue($product)!= '') {
                $packOptionValue = $product->getResource()->getAttribute('pack_of')->getFrontend()->getValue($product);
            } else { $packOptionValue = '';}

            $isLotController = ($product->getData('is_lot_controlled') == 1) ? 'YES':'NO';

            $stockStatus = $this->getStockStatus($product->getData('entity_id'));

            $basePrice = $product->getData('price');
            $product['sku'] = $product->getData('sku');
            $product['mrp'] = $product->getData('mrp');
            $product['allowed_channels'] = $product->getData('allowed_channels');
            $product['is_in_stock'] = $stockStatus;
            $product['status'] = $product->getData('status');
            $product['is_published'] = $product->getData('is_published');
            $product['department'] = $product->getData('department');
            $product['class'] = $product->getData('class');
            $product['subclass'] = $product->getData('subclass');
            $product['meta_title'] = $product->getData('meta_title');
            $product['is_lot_controlled'] = $isLotController;
            $product['lot_control_parameters'] = $product->getData('lot_control_parameters');
            $product['case_config'] = $product->getData('case_config');
            $product['pack_of'] = $packOptionValue;
            //------Tier price data------
            $productObject = $this->catalogProduct->load($key);
            $tier_price = $productObject->getTierPrices();
                $result = $this->getTierPrice([$product->getData('sku')]);
            if (count($result)) {
                $proDataArr = [];
                foreach ($result as $item) {
                    $proDataArr['sku'] = $product['sku'];

                    if($product['status'] == 1) {                        
                        $proDataArr['status'] = 'ENABLED';
                    } else{ $proDataArr['status'] = 'DISABLED'; }

                    $proDataArr['is_in_stock'] = $stockStatus;

                    if($product['is_published'] == 1) {                        
                        $proDataArr['is_published'] = 'YES';
                    } else{ $proDataArr['is_published'] = 'NO'; }    

                    $proDataArr['meta_title'] = $product['meta_title'];
                    $proDataArr['department'] = $product['department'];
                    $proDataArr['class'] = $product['class'];
                    $proDataArr['subclass'] = $product['subclass'];
                    $proDataArr['is_lot_controlled'] = $isLotController;
                    $proDataArr['lot_control_parameters'] = $product['lot_control_parameters'];
                    $proDataArr['case_config'] = $product['case_config'];
                    $proDataArr['pack_of'] = $packOptionValue;
                    $proDataArr['mrp'] = $product['mrp'];
                    $proDataArr['allowed_channels'] = $product['allowed_channels'];
                    
                    $proDataArr['tier_price_customer_group'] = $item->getData('customer_group');
                    $proDataArr['tier_price_qty'] = $item->getData('quantity'); 

                    if($isIncludingTax == 1){
                        $proDataArr['tier_price_with_tax'] = $item->getData('price');
                        $proDataArr['tier_price_without_tax'] = $item->getData('price') * 100/ (100 + $taxCalculationRateValue);                        
                    } else {
                         $proDataArr['tier_price_with_tax'] = $item->getData('price') + ($item->getData('price') * $taxCalculationRateValue) / 100;
                        $proDataArr['tier_price_without_tax'] = $item->getData('price');
                    }

                    $productData = $proDataArr;
                    $productFinalData[] = $productData;
                }
            }
        }
        return $productFinalData;
    }


    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getDefaultValues()
    {
        $values = [];

        return $values;
    }

    /**
     * tier price result
     *
     * @param array $sku
     * @return TierPriceInterface[]
     */
    public function getTierPrice(array $sku)
    {
        $result = [];
        try {
             $result = $this->tierPrice->get($sku);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        return $result;
    }

    /**
     * get stock status
     *
     * @param int $productId
     * @return bool
     */
    public function getStockStatus($productId)
    {
        /** @var StockItemInterface $stockItem */
        $stockItem = $this->stockRegistry->getStockItem($productId);
        $isInStock = $stockItem->getIsInStock();
        $stockStatus = ($isInStock == 1) ? 'In Stock' : 'Out of Stock';
        return $stockStatus;
    }
}
