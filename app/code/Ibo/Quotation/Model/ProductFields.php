<?php

namespace Ibo\Quotation\Model;

use Magento\Eav\Model\Entity\Attribute;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;
use Embitel\ProductImport\Model\Import\CsvProcessor;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;

class ProductFields
{
    /**
     * Default attribute table name.
     */
    public const TABLE_NAME_DEFAULT_ATTRIBUTE = 'embitel_quotation_attribute_default';

    /**
     * Embitel tax master table name.
     */
    public const TABLE_NAME_EMBITEL_TAX_MASTER = 'embitel_tax_master';

    /**
     * Product catalog service push table name.
     */
    public const TABLE_NAME_PRODUCT_CS_PUSH = 'catalog_service_product_push';

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var CsvProcessor
     */
    protected $csvProcessor;

    /**
     * @var AttributeSetRepositoryInterface
     */
    protected $attributeSetRepository;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * DB Connection
     *
     * @var type
     */
    protected $connection = null;

    /**
     * @param Attribute $attribute
     * @param AttributeRepository $attributeRepository
     * @param CsvProcessor $csvProcessor
     * @param AttributeSetRepositoryInterface $attributeSetRepository
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Attribute $attribute,
        AttributeRepository $attributeRepository,
        CsvProcessor $csvProcessor,
        AttributeSetRepositoryInterface $attributeSetRepository,
        ResourceConnection $resourceConnection
    ) {
        $this->attribute = $attribute;
        $this->attributeRepository = $attributeRepository;
        $this->csvProcessor = $csvProcessor;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
    }

    /**
     * Default fields
     */
    public function getDefaultFields()
    {
        $this->log("Get hardcoded default fields");
        return [
            'allowed_channels' => 'STORE',
            'store_fulfilment_mode' => 'DWH',
            'service_category' => 'LOCAL',
            'courier_type' => 'F',
            //'replenishability' => CL,
            'status' => ProductStatus::STATUS_ENABLED, //is_active
            'is_returnable' => 'No',
            'pod_eligible' => 0,
            'is_lot_controlled' => 0,
            'is_catalog_sales' => 1,
            'non_catalog' => 1,
            'is_product_having_manual_title' => 0,
            'allow_simple_product_to_buy' => 1
        ];
    }

    /**
     * Get list of fields which are allowed to update in the product.
     *
     * @return type
     */
    public function getAllowedFieldsForUpdate()
    {
        return [
            'hsn_code',
            'vendor_id',
            'vendor_sku_id',
            'vendor_sku_title',
            'mrp'
        ];
    }

    /**
     * Get list of fields which odoo considers for update
     *
     * @return type
     */
    public function getOdooUpdateFields()
    {
        return [
            'name',
            'mrp',
            'unique_group_id',
            'hsn_code',
            'barcode',
            'esin',
            'sale_uom',
            'department',
            'class',
            'subclass',
            'brand_Id',
            'is_bom',
            'is_active_for_purchase',
            'is_lot_controlled',
            'lot_control_parameters',
            'is_catalog_sales',
            'replenishability',
            'replenishability_action'
        ];
    }

    /**
     * Get some required fields.
     *
     * @param type $attributeSetId
     * @return type
     */
    public function getRequiredFieldName($attributeSetId)
    {
        $this->log("Get mandatory attribute codes of attribute set");
        $requiredAttribute = [];
        $attributeCollection = $this->attribute->getCollection()
            ->addFieldToFilter("main_table.entity_type_id", 4)
            ->addFieldToFilter("is_required", "1");

        $jointable = 'eav_entity_attribute';
        $attributeCollection->getSelect()
            ->join(
                ['ot'=>$jointable],
                "main_table.attribute_id = ot.attribute_id AND ot.attribute_set_id = ".$attributeSetId
            );

        $notRequired = ['created_at','updated_at','sku','esin','name', 'qty', 'brand_Id', 'brand_model_number',
            'production_start_date','slug','giftcard_type','links_purchased_separately', 'is_catalog_sales', 'price',
            'links_title','price_type','samples_title','price_view','shipment_type','sku_type','weight_type',
            'unique_group_id', 'hsn_code', 'replenishability', 'ebo_grading'];

        $defaultFields = $this->getDefaultFields();
        foreach ($attributeCollection as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            if (!in_array($attributeCode, $notRequired)
                    && !in_array($attributeCode, array_keys($defaultFields))) {
                $requiredAttribute[] = $attributeCode;
            }
        }

        $this->log("Get variant attributes - START");
        $variantAttributes = $this->getVariantAttributes($attributeSetId);
        if (!empty($variantAttributes)) {
            foreach ($variantAttributes as $variantAttribute) {
                if (!in_array(trim($variantAttribute), $requiredAttribute)) {
                    $requiredAttribute[] = trim($variantAttribute);
                }
            }
        }
        $this->log("Get variant attributes - END");
        return array_unique($requiredAttribute);
    }

    /**
     * Get variant attributes
     *
     * @param type $attributeSetId
     * @return type
     */
    public function getVariantAttributes($attributeSetId)
    {
        $variants = [];
        try {
            $attributeSet = $this->attributeSetRepository->get($attributeSetId);
            $attributeSetCode = $attributeSet->getAttributeSetName();
            $csvRow = $this->csvProcessor->getEvalFormula($attributeSetCode);
            if (isset($csvRow['AttributeSet']) && $csvRow['AttributeSet'] == $attributeSetCode) {
                if (isset($csvRow['ConfigurableAttributes']) && trim($csvRow['ConfigurableAttributes']) != '') {
                    foreach (explode(",", trim($csvRow['ConfigurableAttributes'])) as $confAttributeCode) {
                        try {
                            $variants[] = trim($confAttributeCode);
                        } catch (\Exception $ex) {
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            return $variants;
        }

        return $variants;
    }

    /**
     * Get default values
     *
     * @param type $attributeSetId
     * @return type
     */
    public function getDefaultValues($attributeSetId)
    {
        $this->log("Get product attribute data - START");
        $requiredAttributes = $this->getRequiredFieldName($attributeSetId);
        $this->log("Get default fields from table - START");
        $existingRecords = $this->getExistingDefaultValues();
        $this->log("Get default fields from table - END");
        $defaultFields = $this->getDefaultFields();
        $data = [];
        $this->log("Before preparing data using default OR attribute first lov");
        $requiredAttributes = array_merge($requiredAttributes, array_keys($defaultFields));
        foreach ($requiredAttributes as $attributeCode) {
            if (array_key_exists($attributeCode, $existingRecords) && isset($existingRecords[$attributeCode])) {
                $data[$attributeCode] = $existingRecords[$attributeCode];
            } else {
                $data[$attributeCode] = $this->getAttributeValue($attributeCode, $defaultFields);
            }
        }
        $this->log("Get product attribute data - END");
        return $data;
    }

    /**
     * Get attribute value
     *
     * @param type $attributeCode
     * @param type $defaultFields
     * @return string|int
     */
    public function getAttributeValue($attributeCode, $defaultFields)
    {
        $attribute = $this->attributeRepository->get($attributeCode);
        if ($attribute->getFrontendInput() == 'select') {
            if (array_key_exists($attributeCode, $defaultFields)) {
                $optionId = $attribute->getSource()->getOptionId($defaultFields[$attributeCode]);
                $this->addAttributeToDbRecord($attributeCode, $optionId, $defaultFields[$attributeCode]);
                return $optionId;
            } else {
                return $this->getAttributeOption($attribute);
            }
        } elseif ($attribute->getFrontendInput() == 'boolean') {
            if (array_key_exists($attributeCode, $defaultFields)) {
                if (in_array(strtolower($defaultFields[$attributeCode]), [1, 'yes', true])) {
                    $this->addAttributeToDbRecord($attributeCode, 1, 'Yes');
                    return 1;
                }
            }
            $this->addAttributeToDbRecord($attributeCode, 0, 'No');
            return 0;
        } else {
            if (array_key_exists($attributeCode, $defaultFields)) {
                $this->addAttributeToDbRecord($attributeCode, $defaultFields[$attributeCode], $defaultFields[$attributeCode]);
                return $defaultFields[$attributeCode];
            } else {
                $this->addAttributeToDbRecord($attributeCode, 'NA', 'NA');
                return 'NA';
            }
        }
    }

    /**
     * Get attribute first option value
     *
     * @param type $attribute
     * @return type
     */
    public function getAttributeOption($attribute)
    {
        $options = $attribute->getOptions();
        foreach ($options as $option) {
            if ($option->getValue() != '') {
                $this->addAttributeToDbRecord($attribute->getAttributeCode(), $option->getValue(), $option->getLabel());
                return $option->getValue();
            }
        }
    }

    /**
     * Get all the records of default values from the quotation table
     *
     * @return type
     */
    public function getExistingDefaultValues()
    {
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME_DEFAULT_ATTRIBUTE);
        $select = $this->connection->select()
            ->from(
                ['c' => $tableName],
                ['attribute_code', 'value']
            );
        return $this->connection->fetchPairs($select);
    }

    /**
     * Add new record to default value table
     *
     * @param type $attributeCode
     * @param type $value
     * @param type $label
     */
    public function addAttributeToDbRecord($attributeCode, $value, $label)
    {
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME_DEFAULT_ATTRIBUTE);
        $data = [
            'attribute_code' => $attributeCode,
            'value' => $value,
            'label' => $label
        ];

        $this->connection->insert($tableName, $data);
    }

    /**
     * Get tax class from tax master
     *
     * @param type $productData
     * @return boolean
     */
    public function getTaxClass($productData)
    {
        if (array_key_exists('hsn_code', $productData) && $productData['hsn_code'] != '') {
            $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME_EMBITEL_TAX_MASTER);
            $select = $this->connection->select()
                ->from(
                    ['c' => $tableName],
                    ['tax_class_id']
                )->where("c.hsn_code = ?", $productData['hsn_code']);
            return $this->connection->fetchOne($select);
        }
        return false;
    }

    /**
     * Get product tax class by hsn code
     *
     * @param type $productData
     * @return boolean
     */
    public function getTaxClassId($productData)
    {
        $taxclass = $this->getTaxClass($productData);
        if ($taxclass) {
            $attribute = $this->attributeRepository->get('tax_class_id');
            $options = $attribute->getOptions();
            foreach ($options as $option) {
                if ($option->getLabel() == $taxclass) {
                    return $option->getValue();
                }
            }
        }
        return false;
    }

    /**
     * Get product catalog service push status flag
     *
     * @param type $productId
     * @return type
     */
    public function getProductCsStatusSync($productId)
    {
        $select = $this->connection->select()
            ->from(
                ['c' => self::TABLE_NAME_PRODUCT_CS_PUSH],
                ['status_flag']
            )->where("c.product_id = ?", $productId);
        return $this->connection->fetchOne($select);
    }

    /**
     * Log
     *
     * @param type $message
     * @return type
     */
    public function log($message)
    {
        $logFileName = BP . '/var/log/quotation_apis.log';
        $writer = new \Zend\Log\Writer\Stream($logFileName);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        if (is_array($message)) {
            $logger->info(print_r($message, true));
        } else {
            $logger->info($message);
        }
    }
}
