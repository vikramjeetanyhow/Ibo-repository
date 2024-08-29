<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Controller\Adminhtml\Updatecontrol;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\File\Csv;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Ibo\ProductUpdate\Model\ResourceModel\Subclass\CollectionFactory;
use Magento\Catalog\Model\ProductFactory;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;


class Lotcontrolupdate extends \Ibo\ProductUpdate\Controller\Adminhtml\Updatecontrol
{
    /**
     * @var Csv
     */
    protected $csv;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ProductAction
     */
    private $productAction;

    protected $products = [];

    protected $filteredCsvData = [];

    protected $validAttributes = false;

    /**
     * @param Csv $csv
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Context $context,
        ProductFactory $productFactory,
        CollectionFactory $subclassCollection,
        ProductAction $action,
        FileFactory $fileFactory,
        Csv $csv,
        ProductPushHelper $productPushHelper
    ) {
        parent::__construct($context,$fileFactory);
        $this->csv = $csv;
        $this->productFactory = $productFactory;
        $this->productAction = $action;
        $this->subclassCol = $subclassCollection;
        $this->productPushHelper = $productPushHelper;
    }

    /**
     * Read CSV data
     *
     * @param type $fileName
     * @return type
     */
    public function getCsvData($fileName)
    {
        return $this->csv->getData($fileName);
    }

    /**
     * Product import action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $lotcontrolImportFile = $this->getRequest()->getFiles('controlimport_file');
        if ($this->getRequest()->isPost() && isset($lotcontrolImportFile['tmp_name'])) {
            try {

                $header = [];
                $importData = [];
                $pathinfo = pathinfo($lotcontrolImportFile['name']);
                if (!isset($lotcontrolImportFile['tmp_name'])) {
                    throw new LocalizedException(__('Invalid file upload attempt.'));
                }
                if (!in_array($pathinfo['extension'], ['csv'])) {
                    throw new LocalizedException(__('Please upload CSV file.'));
                }
                $csvData = $this->getCsvData($lotcontrolImportFile['tmp_name']);
                if (empty($csvData)) {
                    throw new LocalizedException(__('File is empty'));
                }
                foreach ($csvData as $rowIndex => $dataRow) {
                    if ($rowIndex == 0) {
                        $header = $dataRow;
                        continue;
                    }
                    $this->validateRowData($header, $dataRow);
                }
                if (isset($this->filteredCsvData['failure'])) {
                    $this->products['failure'] = $this->filteredCsvData['failure'];
                }
                $this->updateProducts($this->filteredCsvData['update']);
                $this->writeLog(print_r($this->products,true));
                $this->messageManager->addSuccess(__('lotControl data has been Updated.'));

            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addError(__('Invalid file upload attempt' . $e->getMessage()));
            }
        } else {
            $this->messageManager->addError(__('Invalid file upload attempt'));
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRedirectUrl());
        return $resultRedirect;
    }

        /**
     * Validate CSV data
     *
     * @param type $header
     * @param type $dataRow
     * @return boolean
     */
    private function validateRowData($header, $dataRow)
    {
        $isRawValid = true;
        $data = array_combine($header, $dataRow);
        
        if (!$this->validAttributes) {
            $validAttributes = ['sku','is_lot_controlled','lot_control_parameters'];
            $validAttributes = array_combine($validAttributes, $validAttributes);
            $additionalFields = array_diff_key($data, $validAttributes);
            if (count($additionalFields) > 0) {
                throw new LocalizedException(__('Some additional attributes are added or missing in CSV. Please remove and try again. Additional attributes are: ' . implode(", ", array_keys($additionalFields))));
            }
            $this->validAttributes = true;
        }

        if(!in_array('sku', $header)){
            $data['error'] = 'sku column missing.';
            $this->filteredCsvData['failure'][] = $data;
            return true;
        }

        if (!isset($data['sku']) || !trim($data['sku'])) {
            $data['error'] = 'sku field missing.';
            $this->filteredCsvData['failure'][] = $data;
            return true;
        }

        if(!in_array('lot_control_parameters', $header)) {
            $invalidFields[] = 'lot_control_parameters';
            $isRawValid = false;
        }

        if(!in_array('is_lot_controlled', $header) && (!isset($data['is_lot_controlled']) || !trim($data['is_lot_controlled']))) {
            $invalidFields[] = 'is_lot_controlled';
            $isRawValid = false;
        }

        if ($isRawValid) {
            $this->filteredCsvData['success'][$data['sku']][] = $data;
            $this->filteredCsvData['update'][] = $data;
            return true;
        } else {
            $data['error'] = "Invalid fields: ";
            $data['error'] .= implode(',', $invalidFields);
            $this->filteredCsvData['failure'][] = $data;
            return false;
        }

    }

        /**
     * Update product by sku.
     *
     * @param type $raws
     */
    private function updateProducts($raws)
    {   
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $flag = true;
        foreach ($raws as $rowData) {
            $this->writeLog('Import - Start sku => '.$rowData['sku']);

            //Skip empty rows
            if (!isset($rowData['sku']) || !isset($rowData['is_lot_controlled'])) {
                continue;
            }

            $sku = trim($rowData['sku']);
            $isLotControlled = 0;
            if (strtolower($rowData['is_lot_controlled']) == 'yes' || trim($rowData['is_lot_controlled']) == 1 || trim($rowData['is_lot_controlled']) == '1') {
                $isLotControlled = 1;
            } elseif (strtolower(trim($rowData['is_lot_controlled'])) == 'no' || trim($rowData['is_lot_controlled']) === 0 || trim($rowData['is_lot_controlled']) === '0') {
                $isLotControlled = 0;
            } else {
                throw new LocalizedException(__("Please check is_lot_controlled param for sku: " . $sku));
                //echo "Please check is_lot_controlled param for sku: " . $sku . "\n";
                continue;
            }

            if (strtolower(trim($rowData['is_lot_controlled'])) == 'no' && strtolower($rowData['lot_control_parameters']) !== "") {
                throw new LocalizedException(__("Please check 'is_lot_controlled value is NO for sku: " . $sku." So, for 'lot_control_parameters' should Always be Blank"));
                //echo "Please check is_lot_controlled param for sku: " . $sku . "\n";
                continue;
            }

            if (strtolower(trim($rowData['is_lot_controlled'])) == 'yes' && strtolower($rowData['lot_control_parameters']) !== "mrp") {
                throw new LocalizedException(__("Please check 'is_lot_controlled value is YES for sku: " . $sku." So, for 'lot_control_parameters' should Always be MRP"));
                //echo "Please check is_lot_controlled param for sku: " . $sku . "\n";
                continue;
            }

            try {
                $productFactory = $objectManager->get("Magento\Catalog\Model\ProductFactory")->create();
                $product = $productFactory->loadByAttribute('sku', $sku);
                if (!$product) {
                    throw new LocalizedException(__("product not exist with sku:" . $sku));
                   // echo "product not exist with sku:" . $sku . "\n";
                    continue;
                }

                $odooSyncUpdateAttributes = $product->getData('oodo_sync_update');

                if (strlen($odooSyncUpdateAttributes)) {
                    $odooSyncUpdateAttributes = explode(",", $product->getData('oodo_sync_update'));
                } else {
                    $odooSyncUpdateAttributes = [];
                }

                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "is_lot_controlled");
                $odooSyncUpdateAttributes = $this->getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, "lot_control_parameters");

                if (is_array($odooSyncUpdateAttributes)) {
                    $odooSyncUpdateAttributes = array_filter($odooSyncUpdateAttributes, 'strlen');
                    $odooSyncUpdateAttributes = implode(",", $odooSyncUpdateAttributes);
                }
                
                $data = ['is_lot_controlled' => $isLotControlled, 'lot_control_parameters' => $rowData['lot_control_parameters']];
                if ($odooSyncUpdateAttributes != $product->getOdooSyncUpdate()) {
                    $data['oodo_sync_update'] = $odooSyncUpdateAttributes;
                }
                $data['oodo_sync_count'] = 100;

                $productActionObject = $objectManager->get('Magento\Catalog\Model\Product\Action');
                $productActionObject->updateAttributes([$product->getId()], $data, 0);

                $iboHelper = $objectManager->get("Ibo\CoreMedia\Helper\Data");
                $iboHelper->updateCatalogServicePushData($product->getId());
        
                if ($product->getTypeId() != 'simple') {
                    continue;
                }

                //Set title to configurable product
                $parentIds = $objectManager->get('Magento\ConfigurableProduct\Model\Product\Type\Configurable')
                        ->getParentIdsByChild($product->getId());
                if (empty($parentIds)) {
                    continue;
                }
                $parentId = array_shift($parentIds);

                $configProduct = $objectManager->create('Magento\Catalog\Model\Product')->load($parentId);
                $_children = $configProduct->getTypeInstance()->getUsedProducts($configProduct);
                $childs = [];
                foreach ($_children as $child) {
                    $childs[] = $child->getId();
                }
                
                if (min($childs) == $product->getId()) {
                    //update simple product title to configurable.
                    $confiProductModel = $objectManager->get("Magento\Catalog\Model\ProductFactory")->create();
                    $confiProduct = $confiProductModel->load($parentId);
                   // echo $confiProduct->getSku() . "=>" . $isLotControlled . "=>" . $rowData['lot_control_parameters'] . "\n";
                    $productActionObject->updateAttributes([$confiProduct->getId()], ['is_lot_controlled' => $isLotControlled, 'lot_control_parameters' => $rowData['lot_control_parameters']], 0);
                    
                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }

        }
    }

    function getOdooSyncUpdateAttributes($odooSyncUpdateAttributes, $attribute)
    {
        if (is_array($odooSyncUpdateAttributes)) {
            if (!in_array($attribute, $odooSyncUpdateAttributes)) {
                $odooSyncUpdateAttributes[] = $attribute;
            }
        }
        return $odooSyncUpdateAttributes;
    }

    /* log for an Import data */
    public function writeLog($log)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/wms_lotcontrolupdate.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($log);
    }

        /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(
            'Ibo_ProductUpdate::updatelotcontrol'
        );
    }
}
