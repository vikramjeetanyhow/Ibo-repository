<?php
namespace Embitel\TaxMaster\Model;

use Embitel\TaxMaster\Api\TaxUpdateRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Tax\Model\TaxClass\Source\Product as ProductTaxClassSource;
use Magento\Catalog\Model\ProductFactory as CatalogProduct;
use Magento\Framework\App\State;

class TaxUpdateRepository implements TaxUpdateRepositoryInterface
{
    /**
     * @var $productCollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var $productAction
     */
    protected $productAction;

    /**
     * @var CatalogProduct
     */
    protected $catalogProduct;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var $productTaxClassSource
     */
    protected $productTaxClassSource;

    protected $taxClasses = [];

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param ProductAction $productAction
     * @param CatalogProduct $catalogProduct
     * @param State $state
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductTaxClassSource $productTaxClassSource
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        ProductAction $productAction,
        CatalogProduct $catalogProduct,
        State $state,
        ScopeConfigInterface $scopeConfig,
        ProductTaxClassSource $productTaxClassSource
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAction =  $productAction;
        $this->catalogProduct = $catalogProduct;
        $this->state = $state;
        $this->_scopeConfig = $scopeConfig;
        $this->productTaxClassSource = $productTaxClassSource;
    }

    /**
     * Update product's tax by HSN code
     *
     * @param string $hsnCode
     * @param int $taxClass
     * @return boolean
     * @throws CouldNotSaveException
     */
    public function save($hsnCode, $taxClass)
    {
        try {
            if (trim($taxClass) == '' || trim($taxClass) == null) {
                throw new CouldNotSaveException(
                    __('Tax class does not exist for given HSN Code: '.$hsnCode)
                );
            }
            if(!empty($hsnCode) && strpos($hsnCode, "\n") == FALSE) {
                $taxClassIds = $this->getTaxClassIds();
                $taxClassId = '';
                if (array_key_exists($taxClass, $taxClassIds) && $taxClassIds[$taxClass] != '') {
                    $taxClassId = $taxClassIds[$taxClass];
                } else {
                    throw new CouldNotSaveException(
                        __('Tax class "'.$taxClass.'" does not exist for given HSN Code: '.$hsnCode)
                    );
                }

                $collection = $this->productCollectionFactory->create();
                $collection->addAttributeToFilter('hsn_code', $hsnCode);
                $collection->addAttributeToFilter([
                    ['attribute' => 'tax_class_id', 'null' => true],
                    ['attribute' => 'tax_class_id', 'neq' => $taxClassId]
                ]);

                if ($collection->getSize() == 0) {
                    if ($this->state->getAreaCode() != "crontab") {
                        throw new CouldNotSaveException(__('There are no product to update tax class for given HSN Code: '.$hsnCode));
                    }
                    return true;
                }

                if ($taxClassId != 'null') {
                    $productEntityId = $collection->getAllIds();
                    if (!empty($productEntityId)) {
                        $this->productAction->updateAttributes($productEntityId, ['tax_class_id' => $taxClassId, 'two_step_status_cron' => 0], 0);
                        if ($this->state->getAreaCode() == "crontab") {
                            $this->addLog("Total product(s) updated: " . count($productEntityId));
                        }
                    }
                }
            }
        } catch (LocalizedException $exception) {
            $message = 'Could not update tax for hsn code: '.$hsnCode;
            if ($exception->getMessage() != '') {
                $message = $exception->getMessage();
            }
            if ($this->state->getAreaCode() != "crontab") {
                throw new LocalizedException(
                    __($message),
                    $exception
                );
            } else {
                $this->addLog('Error:' .$message);
            }
        }
        return true;
    }

    /**
     * Get tax class IDs
     *
     * @return type
     */
    public function getTaxClassIds()
    {
        if (!empty($this->taxClasses)) {
            return $this->taxClasses;
        }

        $taxClasses = $this->productTaxClassSource->getAllOptions();
        foreach ($taxClasses as $taxClassData) {
            if ($taxClassData['value'] != 0) {
                $this->taxClasses[$taxClassData['label']] = $taxClassData['value'];
            }
        }
        return $this->taxClasses;
    }

    /**
     * Add Log
     *
     * @param string $logdata
     *
     * @return Log Data
     */
    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/taxrate-synced.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            if ($logdata) {
                $logger->info($logdata);
            } else {
                $logger->info($logdata);
            }
        }
    }

    /**
     * Check Log enable or not
     *
     * @return bool
     */
    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->_scopeConfig->getValue(
                "tax_master/cron_settings/statusupdate_log_active"
            );
        }
        return $this->isLogEnable;
    }
}
