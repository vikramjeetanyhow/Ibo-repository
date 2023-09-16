<?php
namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class MoreInfo
{
    public function __construct(
        PriceCurrencyInterface $priceCurrency,
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->priceCurrency = $priceCurrency; 
        $this->resourceConnection = $resourceConnection;

    }

    /**
     * $excludeAttr is optional array of attribute codes to exclude them from additional data array
     *
     * @param $product
     * @param array $excludeAttr
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAdditionalData($_product,array $excludeAttr = [])
    {
        $data = [];
        // $product = $this->productRepository->getById($_product->getId(),false);
        $prodId = $_product->getId();
        $connection = $this->resourceConnection->getConnection();
        //For Select query
        $query = "(SELECT e.attribute_code,e.frontend_label, 'varchar' AS type, v.store_id, v.value,cea.product_specs_position FROM catalog_product_entity_varchar v JOIN eav_attribute e ON e.attribute_id = v.attribute_id JOIN catalog_eav_attribute AS cea ON cea.attribute_id=e.attribute_id and cea.used_in_product_specs = 1  WHERE v.entity_id = ".$prodId.") UNION (SELECT e.attribute_code,e.frontend_label, 'datetime' AS type, v.store_id, v.value,cea.product_specs_position FROM catalog_product_entity_datetime v JOIN eav_attribute e ON e.attribute_id = v.attribute_id JOIN catalog_eav_attribute AS cea ON cea.attribute_id=e.attribute_id and cea.used_in_product_specs = 1 WHERE v.entity_id = ".$prodId.") UNION (SELECT e.attribute_code,e.frontend_label, 'decimal' AS type, v.store_id, v.value,cea.product_specs_position FROM catalog_product_entity_decimal v JOIN eav_attribute e ON e.attribute_id = v.attribute_id JOIN catalog_eav_attribute AS cea ON cea.attribute_id=e.attribute_id and cea.used_in_product_specs = 1 WHERE v.entity_id = ".$prodId.") UNION (SELECT e.attribute_code,e.frontend_label, 'int' AS type, v.store_id, v.value,cea.product_specs_position FROM catalog_product_entity_int v JOIN eav_attribute e ON e.attribute_id = v.attribute_id JOIN catalog_eav_attribute AS cea ON cea.attribute_id=e.attribute_id and cea.used_in_product_specs = 1 WHERE v.entity_id = ".$prodId.") UNION (SELECT e.attribute_code,e.frontend_label, 'text' AS type, v.store_id, v.value,cea.product_specs_position FROM catalog_product_entity_text v JOIN eav_attribute e ON e.attribute_id = v.attribute_id JOIN catalog_eav_attribute AS cea ON cea.attribute_id=e.attribute_id and cea.used_in_product_specs = 1 WHERE v.entity_id = ".$prodId.") ORDER BY attribute_code";
        $attributes = $connection->fetchAll($query);

        $perUnitPriceUnit = $_product->getPerUnitPriceUnit();
        foreach ($attributes as $attribute) {   
                $value = $attribute['value'];
                if ($attribute['type'] == "varchar") {
                    $value = (string)$value;
                } elseif ($attribute['type'] == 'decimal' && is_string($value)) {
                    $value = $this->priceCurrency->convertAndFormat($value);
                } elseif ($attribute['type'] == 'int' && is_string($value)) {
                    $optionId = $value;
                    $attr = $_product->getResource()->getAttribute($attribute['attribute_code']);
                    if ($attr->usesSource()) {
                        $value = $_product->getResource()->getAttribute($attribute['attribute_code'])->setStoreId(1)->getFrontend()->getValue($_product);
                    }
                }
                if($attribute['attribute_code'] == 'coverage') {
                    $value = $value.' '.$perUnitPriceUnit;
                }
                $naText = strtoupper(trim($value));   
                if (is_string($value) && strlen(trim($value)) && $naText != 'NA') {
                    $position = empty($attribute['product_specs_position']) ? 99999 : $attribute['product_specs_position'];
                    $data[$position] = [
                        'label' => $attribute['frontend_label'],
                        'value' => $value,
                        'code' => $attribute['attribute_code']
                    ];
                }
        }
        ksort($data);
        return $data;
    }

    /**
     * Determine if we should display the attribute on the front-end
     *
     * @param \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute
     * @param array $excludeAttr
     * @return bool
     */
    protected function isVisibleOnFrontend(
        \Magento\Eav\Model\Entity\Attribute\AbstractAttribute $attribute,
        array $excludeAttr
    ) {
        return $attribute->getUsedInProductSpecs();
    }
}
