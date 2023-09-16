<?php

namespace Embitel\Facade\Observer;

use Embitel\Facade\Model\FacadeHistory;
use Embitel\Facade\Model\FacadeHistoryFactory;
use Embitel\Facade\Model\ResourceModel\FacadeHistory as FacadeHistoryResourceModel;
use Embitel\Facade\Model\OodoApiHelper;
use Exception;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ResourceModel\ProductFactory as ProductResourceFactory;
use Embitel\Facade\Model\Api;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;

class ProductSaveAfter implements ObserverInterface
{
    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductResourceFactory
     */
    protected $productResourceFactory;

    /**
     * @var Api
     */
    protected $facadeApi;
    private OodoApiHelper $oodoApiHelper;
    private FacadeHistoryFactory $facadeHistoryFactory;
    private FacadeHistoryResourceModel $facadeHistoryResourceModel;

    /**
     * @param ProductFactory $productFactory
     * @param ProductResourceFactory $productResourceFactory
     * @param Api $facadeApi
     * @param OodoApiHelper $oodoApiHelper
     */
    public function __construct(
        ProductFactory $productFactory,
        ProductResourceFactory
        $productResourceFactory,
        Api $facadeApi,
        OodoApiHelper $oodoApiHelper,
        \Magento\Framework\App\ResourceConnection $resource,
        FacadeHistoryFactory $facadeHistoryFactory,
        FacadeHistoryResourceModel $facadeHistoryResourceModel,
        ProductPushHelper $productPushHelper
    )
    {
        $this->productFactory = $productFactory;
        $this->productResourceFactory = $productResourceFactory;
        $this->facadeApi = $facadeApi;
        $this->oodoApiHelper = $oodoApiHelper;
        $this->resource = $resource;
        $this->facadeHistoryFactory = $facadeHistoryFactory;
        $this->facadeHistoryResourceModel = $facadeHistoryResourceModel;
        $this->productPushHelper = $productPushHelper;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getProduct();

        try {
            if ($product->getTypeId() == 'simple') {
                $this->facadeApi->addLog('Entered the facade condition start with sku: ' . $product->getSku());
                try {
                    $facadeHistory = $this->facadeHistoryFactory->create();
                    $this->facadeHistoryResourceModel->load($facadeHistory, $product->getSku(), 'sku');
                    if (null == $facadeHistory->getId()) {
                        $facadeHistory->setSku($product->getSku());
                        $this->facadeHistoryResourceModel->save($facadeHistory);
                    }
                } catch (\Exception $exception) {
                    $this->facadeApi->log('For SKU: ' . $product->getSku() . ', Error Message: ' .$exception->getMessage());
                }
                $this->facadeApi->addLog('Entered the facade condition end');
            }
            if ($product->getTypeId() == 'simple') {
                $this->facadeApi->addLog(__METHOD__);
                //$this->updateCatalogServiceSyncCount($product->getId(),$product->getSku());
                $this->productPushHelper->updateCatalogServicePushData($product->getId());
            }
        } catch (Exception $ex) {
            $this->facadeApi->addLog(__METHOD__);
            $this->facadeApi->addLog("Error on product save: " . $ex->getMessage());
        }

        if ($product->getOodoSyncCount() == 100) {
            $this->oodoApiHelper->addLog("debug start");

            $odooSyncUpdateAttributes = $product->getData('oodo_sync_update');

            if (strlen($odooSyncUpdateAttributes)) {
                $odooSyncUpdateAttributes = explode(",", $product->getData('oodo_sync_update'));
            } else {
                $odooSyncUpdateAttributes = [];
            }

            if ($product->dataHasChangedFor("name")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "name");
            }

            if ($product->dataHasChangedFor("mrp")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "mrp");
            }

            if ($product->dataHasChangedFor("unique_group_id")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "unique_group_id");
            }

            if ($product->dataHasChangedFor("hsn_code")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "hsn_code");
            }

            if ($product->dataHasChangedFor("barcode")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "barcode");
            }

            if ($product->dataHasChangedFor("esin")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "esin");
            }

            if ($product->dataHasChangedFor("sale_uom")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "sale_uom");
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "purchase_uom");
            }

            if ($product->dataHasChangedFor("department")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "department");
            }

            if ($product->dataHasChangedFor("class")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "class");
            }

            if ($product->dataHasChangedFor("subclass")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "subclass");
            }

            if ($product->dataHasChangedFor("brand_Id")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "brand_Id");
            }

            if ($product->dataHasChangedFor("is_bom") || $product->dataHasChangedFor("inventory_basis") ||  $product->dataHasChangedFor("base_offer_id") || $product->dataHasChangedFor("secondary_offer_id")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "is_bom");
            }

            if ($product->dataHasChangedFor("is_active_for_purchase")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "is_active_for_purchase");
            }

            if ($product->dataHasChangedFor("is_lot_controlled")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "is_lot_controlled");
            }

            if ($product->dataHasChangedFor("lot_control_parameters")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "lot_control_parameters");
            }

            if ($product->dataHasChangedFor("is_catalog_sales")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "is_catalog_sales");
            }

            if ($product->dataHasChangedFor("replenishability")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "replenishability");
            }

            if ($product->dataHasChangedFor("replenishability_action")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "replenishability_action");
            }

            if ($product->dataHasChangedFor("non_catalog")) {
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "non_catalog");
            }

            if (is_array($odooSyncUpdateAttributes)) {
                $odooSyncUpdateAttributes = array_filter($odooSyncUpdateAttributes, 'strlen');
                $odooSyncUpdateAttributes = implode(",", $odooSyncUpdateAttributes);
            }


            $this->oodoApiHelper->addLog($odooSyncUpdateAttributes);


            if ($odooSyncUpdateAttributes != $product->getOdooSyncUpdate()) {
                $this->updateOdooAttributesData($product->getId(), $odooSyncUpdateAttributes);
            }
            $this->oodoApiHelper->addLog("debug end");

        }


    }


    protected function updateOdooAttributesData($productId, $odooSyncUpdateAttributes)
    {
        $product = $this->productFactory->create()->load($productId);
        $product->setOodoSyncUpdate($odooSyncUpdateAttributes);
        $product->getResource()->saveAttribute($product, 'oodo_sync_update');
    }

    /**
     * @param $odooSyncUpdateAttributes
     * @return mixed
     */
    private function getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, $attribute)
    {
        if (is_array($odooSyncUpdateAttributes)) {
            if (!in_array($attribute, $odooSyncUpdateAttributes)) {
                $odooSyncUpdateAttributes[] = $attribute;
            }
        }
        $this->oodoApiHelper->addLog($odooSyncUpdateAttributes);
        return $odooSyncUpdateAttributes;
    }

}
