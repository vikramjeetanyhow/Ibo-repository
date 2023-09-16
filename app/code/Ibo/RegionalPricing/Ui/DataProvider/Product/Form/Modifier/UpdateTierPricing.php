<?php
namespace Ibo\RegionalPricing\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Ibo\RegionalPricing\Model\Config\Source\Region as CustomerZone;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Element\DataType\Text;

class UpdateTierPricing extends AbstractModifier
{
   /**
    * @var ArrayManager
    * @since 101.0.0
    */
   protected $arrayManager;

   /**
    * @var string
    * @since 101.0.0
    */
   protected $scopeName;

   /**
    * @var array
    * @since 101.0.0
    */
   protected $meta = [];

   /**
     * @var StoreManagerInterface
     * @since 101.0.0
     */
    protected $storeManager;

   /**
    * UpdateTierPricing constructor.
    * @param ArrayManager $arrayManager
    * @param CustomerZone $customerZone
    *
    */
   public function __construct(
       ArrayManager $arrayManager,
       CustomerZone  $customerZone,
       ScopeConfigInterface $scopeConfig,
       StoreManagerInterface $storeManager 
   ) {
    $this->arrayManager = $arrayManager;
    $this->customerZone = $customerZone;
    $this->scopeConfig = $scopeConfig;
    $this->storeManager = $storeManager;
   }

   /**
    * @param array $data
    * @return array
    * @since 100.1.0
    */
   public function modifyData(array $data)
   {
       // TODO: Implement modifyData() method.
       return $data;
   }

   /**
    * @param array $meta
    * @return array
    * @since 100.1.0
    */
   public function modifyMeta(array $meta)
   {
       // TODO: Implement modifyMeta() method.
       $this->meta = $meta;

       $this->customizeTierPrice();

       return $this->meta;
   }

   /**
    * @return $this
    */
   private function customizeTierPrice()
   {
       $tierPricePath = $this->arrayManager->findPath(
            ProductAttributeInterface::CODE_TIER_PRICE,
            $this->meta,
            null,
            'children'
        );

       if ($tierPricePath) {
            $this->meta = $this->arrayManager->merge(
                $tierPricePath,
                $this->meta,
                $this->getTierPriceStructure($tierPricePath)
            );
       }

       return $this;
   }
   private function getTierPriceStructure($tierPricePath)
    {
        return [
            'children' => [
                'record' => [
                    'children' => [
                        'customer_zone' => [
                            'arguments' => [
                                'data' => [
                                    'config' => [
                                        'formElement' => Select::NAME,
                                        'componentType' => Field::NAME,
                                        'dataType' => Text::NAME,
                                        'dataScope' => 'customer_zone',
                                        'label' => __('Zone\Region'),
                                        'options' => $this->getCustomerZones(),
                                        'value' => $this->getDefaultCustomerZone(),
                                        'sortOrder' => 22,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

   /**
    * @return array
    */
   public function getCustomerZones()
   {
       $customerZoneOptions = $this->customerZone->toOptionArray();
       return $customerZoneOptions;
   }

   /**
    * @return array
    */
   public function getDefaultCustomerZone()
   {
       return $this->scopeConfig
                   ->getValue("regional_pricing/setting/default_zone",
                              \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                              $this->storeManager->getStore()->getStoreId()
                          );
   }
}