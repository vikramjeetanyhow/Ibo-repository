<?php

namespace Ibo\Quotation\Model;

use Ibo\Quotation\Api\ProductRepositoryInterface;
use Ibo\Quotation\Model\ProductMetadata;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\ResourceConnection;

class ProductRepository implements ProductRepositoryInterface
{
    /**
     * Quotation Odoo Values table name.
     */
    public const TABLE_NAME_QUOTATION_ODOO_VALUES = 'embitel_quotation_odoo_values';
    /**
     * @var ProductMetadata
     */
    protected $productMetadata;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @param ProductMetadata $productMetadata
     * @param ProductFactory $productFactory
     */
    public function __construct(
        ProductMetadata $productMetadata,
        ProductFactory $productFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->productMetadata = $productMetadata;
        $this->productFactory = $productFactory;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
    }

    /**
     * Product Create
     *
     * @param mixed $product
     * @return array
     */
    public function save($product)
    {
        $this->productMetadata->log("------------- START ---------------");
        if (!$this->productMetadata->isModuleEnabled()) {
            $this->productMetadata->log("Quotation functionality is not active.");
            $this->productMetadata->log("------------- END ---------------");
            return [
                [
                    'success' => false,
                    'message' => 'Quotation functionality is not active.'
                ]
            ];
        }
        $this->productMetadata->log("After module status check");
        $params = $product;

        $error = [];
        if (!array_key_exists('ibo_category_id', $params) || trim($params['ibo_category_id']) == '') {
            $error[] = 'ibo_category_id';
        }
        if (!array_key_exists('brand_id', $params) || trim($params['brand_id']) == '') {
            $error[] = 'brand_id';
        }
        if (!array_key_exists('brand_model_number', $params) || trim($params['brand_model_number']) == '') {
            $error[] = 'brand_model_number';
        }
        if (!array_key_exists('hsn_code', $params) || trim($params['hsn_code']) == '') {
            $error[] = 'hsn_code';
        }
        if (array_key_exists('is_catalog_sales', $params) && $params['is_catalog_sales'] === false) {
            $error[] = 'is_catalog_sales';
        }
        if (!array_key_exists('customer_zone', $params) || empty($params['customer_zone'])) {
            $error[] = 'customer_zone';
        }
        if (!array_key_exists('price_without_gst', $params) || trim($params['price_without_gst']) == '') {
            $error[] = 'price_without_gst';
        }
        if (!array_key_exists('absolute_cost_price', $params) || trim($params['absolute_cost_price']) == '') {
            $error[] = 'absolute_cost_price';
        }

        if (!empty($error)) {
            $this->productMetadata->log('Invalid input fields: ' . implode(", ", $error));
            $this->productMetadata->log($params);
            $this->productMetadata->log("------------- END ---------------");
            return [
                [
                    'success' => false,
                    'message' => 'Invalid input field(s): ' . implode(", ", $error)
                ]
            ];
        }

        $this->productMetadata->log("Before unique group id check.");
        //Check if product is already exist with same unique group id.
        $products = $this->productMetadata->getIsUniqueGroupIdExist($params);
        if ($products->getSize() > 0) {
            $this->productMetadata->log("Product is already exist with same ibo_category_id, brand_model_number, brand_Id.");
            $this->productMetadata->log($params);
            $this->productMetadata->log("------------- END ---------------");
            return [
                [
                    'success' => false,
                    'message' => 'Product is already exist with same ibo_category_id, brand_model_number, brand_Id and its offer ID is: ' . $products->getData()[0]['sku']
                ]
            ];
        }
        $this->productMetadata->log("Before product data get.");

        $productData = $this->productMetadata->getProductData($params);
        if (array_key_exists('error_in_create', $productData) && $productData['error_in_create'] != '') {
            $this->productMetadata->log($productData['error_in_create']);
            $this->productMetadata->log($params);
            $this->productMetadata->log("------------- END ---------------");
            return [
                [
                    'success' => false,
                    'message' => $productData['error_in_create']
                ]
            ];
        }

        if (!array_key_exists('sku', $productData) || trim($productData['sku']) == '') {
            $this->productMetadata->log("There is some issue while creating product.");
            $this->productMetadata->log($params);
            $this->productMetadata->log("------------- END ---------------");
            return [
                [
                    'success' => false,
                    'message' => 'There is some issue while creating product.'
                ]
            ];
        }

        $categoryIds = $productData['category_id'];
        unset($productData['category_id']);
        $sku = $productData['sku'];

        try {
            $this->productMetadata->log("product save - START");
            $product = $this->productFactory->create();
            $product->setdata($productData);
            $product->save();

            //put in table cp & sp data
            $this->saveOdooValues($product, $params);

            $this->productMetadata->log("product save - DONE");
            $this->productMetadata->log("product SKU: " . $sku);
            $statusFlag = $this->productMetadata->syncProducts($product->getId(), $sku);
            if ($statusFlag > 0 && $statusFlag != 100) {
                $this->productMetadata->log('Product created but failed to sync to catalog service.');
                $this->productMetadata->log($params);
                $this->productMetadata->log("------------- END ---------------");
                return [
                    [
                        'success' => false,
                        'message' => 'Product ('.$sku.') created but failed to sync to catalog service.'
                    ]
                ];
            }
            $productModelNew = $this->productFactory->create()->load($product->getId());
            $this->productMetadata->syncProductToOodo($productModelNew, 'create');
            $oodoStatusFlag = $this->productFactory->create()->getResource()->getAttributeRawValue($product->getId(), 'oodo_sync_count', 0);
            if ($oodoStatusFlag > 0 && $oodoStatusFlag != 100) {
                $this->productMetadata->log('Product created but failed to sync to oodo.');
                $this->productMetadata->log($params);
                $this->productMetadata->log("------------- END ---------------");
                return [
                    [
                        'success' => false,
                        'message' => "Product (".$product['sku'].") created but failed to sync to oodo."
                    ]
                ];
            }
        } catch (Exception $ex) {
            $this->productMetadata->log('Error while saving product: ' . $ex->getMessage());
            $this->productMetadata->log($params);
            $this->productMetadata->log("------------- END ---------------");
            return [
                [
                    'success' => false,
                    'message' => 'Error while saving product: ' . $ex->getMessage()
                ]
            ];
        }

        $this->productMetadata->log("------------- END ---------------");
        return [
            [
                'success' => true,
                'sku' => $sku
            ]
        ];
    }

    /**
     * Product Update
     *
     * @param mixed $product
     * @return boolean
     */
    public function update($product)
    {
        $this->productMetadata->log("------ Product update START ----------");
        if (!$this->productMetadata->isModuleEnabled()) {
            $this->productMetadata->log("Quotation functionality is not active.");
            $this->productMetadata->log("------ Product update END ----------");
            throw new \Magento\Framework\Webapi\Exception(__("Quotation functionality is not active."));
        }
        $this->productMetadata->log("After module status check");

        if (!array_key_exists('sku', $product) || trim($product['sku']) == '') {
            $this->productMetadata->log("Please pass 'sku' detail in the request.");
            $this->productMetadata->log("------ Product update END ----------");
            throw new \Magento\Framework\Webapi\Exception(__("Please pass 'sku' detail in the request."));
        }

        if (count($product) == 1) {
            $this->productMetadata->log("Please add fields to update.");
            $this->productMetadata->log("------ Product update END ----------");
            throw new \Magento\Framework\Webapi\Exception(__("Please add fields to update."));
        }

        try {
            $productIdModel = $this->productFactory->create();
            $id = $productIdModel->getIdBySku($product['sku']);
            if (!$id) {
                $this->productMetadata->log("Product not found for: " . $product['sku']);
                $this->productMetadata->log("------ Product update END ----------");
                throw new \Magento\Framework\Webapi\Exception(__("Product not found for: " . $product['sku']));
            }

            $productModel = $this->productFactory->create()->load($id);
            $this->productMetadata->log("Get product data for update - START");
            $this->productMetadata->log("SKU:" . $product['sku']);
            $data = $this->productMetadata->getProductDataToUpdate($product, $productModel);
            $this->productMetadata->log($data);
            $this->productMetadata->log("Get product data for update - END");
            $data['store_id'] = 0;
            $productModel->addData($data);
            $this->productMetadata->log("Product update - START");
            $productModel->save();
            $this->productMetadata->log("Product update - DONE");

            $this->productMetadata->log("Product update Sync - START");
            $statusFlag = $this->productMetadata->syncProducts($id, $product['sku']);
            if ($statusFlag > 0 && $statusFlag != 100) {
                $this->productMetadata->log($product);
                throw new \Magento\Framework\Webapi\Exception(__("Product (".$product['sku'].") updated but failed to sync to catalog service."));
            }
            $this->productMetadata->log("Product update Sync - DONE");

            $productModelNew = $this->productFactory->create()->load($id);
            $this->productMetadata->syncProductToOodo($productModelNew, 'update');
            $oodoStatusFlag = $this->productFactory->create()->getResource()->getAttributeRawValue($id, 'oodo_sync_count', 0);
            if ($oodoStatusFlag > 0 && $oodoStatusFlag != 100) {
                $this->productMetadata->log($product);
                throw new \Magento\Framework\Webapi\Exception(__("Product (".$product['sku'].") updated but failed to sync to oodo."));
            }
        } catch (\Exception $ex) {
            $this->productMetadata->log("There is error: " . $ex->getMessage());
            $this->productMetadata->log("------ Product update END ----------");
            throw new \Magento\Framework\Webapi\Exception(__($ex->getMessage()));
        }
        $this->productMetadata->log("------ Product update END ----------");
        return true;
    }

    public function saveOdooValues($product, $params) {
        try {
            //get CP (vendor_pricelist) configurations and dynamic values
            $cpData = $this->productMetadata->getCPConfig($params);
            $cpData['vendor_code'] = $params['vendor_id'] ?? '';
            $cpData['vendor_sku'] = $params['vendor_sku_id'] ?? '';
            $cpData['vendor_sku_title'] = $params['vendor_sku_title'] ?? '';
            $cpData['vendor_hsn_code'] = $params['hsn_code'] ?? '';
            $cpData['absolute_cost_price'] = $params['absolute_cost_price'];

            $tableName = $this->resourceConnection->getTableName(
                self::TABLE_NAME_QUOTATION_ODOO_VALUES);
            $data = [
                'sku' => $product->getSku(),
                'cp_json' => json_encode($cpData)
            ];

            if ($params['price_without_gst'] > 0) {
                //get SP (customer_pricelist)  configurations and dynamic values
                $spData = $this->productMetadata->getSPConfig($params);
                $spData['customer_zone'] = $params['customer_zone'];
                $spData['price_without_gst'] = $params['price_without_gst'];
                $spData['start_date'] = $product->getProductionStartDate();
                $spData['price_relevance_zone'] = $params['customer_zone'];
                $data['sp_json'] = json_encode($spData);
            }

            $this->connection->insert($tableName, $data);
        } catch (\Exception $ex) {
            $this->productMetadata->log("Error in save Odoo values: " . $ex->getMessage());
        }
    }

}
