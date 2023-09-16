<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Controller\Adminhtml\Dimension;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\File\Csv;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Ibo\ProductUpdate\Model\ResourceModel\Subclass\CollectionFactory;
use Magento\Catalog\Model\ProductFactory;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;


class Post extends \Ibo\ProductUpdate\Controller\Adminhtml\Dimension
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
        $dimensionImportFile = $this->getRequest()->getFiles('dimensionimport_file');
        if ($this->getRequest()->isPost() && isset($dimensionImportFile['tmp_name'])) {
            try {

                $header = [];
                $importData = [];
                $pathinfo = pathinfo($dimensionImportFile['name']);
                if (!isset($dimensionImportFile['tmp_name'])) {
                    throw new LocalizedException(__('Invalid file upload attempt.'));
                }
                if (!in_array($pathinfo['extension'], ['csv'])) {
                    throw new LocalizedException(__('Please upload CSV file.'));
                }
                $csvData = $this->getCsvData($dimensionImportFile['tmp_name']);
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
                $this->messageManager->addSuccess(__('The product dimension data has been imported.'));

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
            $validAttributes = ['sku','package_weight_in_kg','package_width_in_cm','package_length_in_cm','package_height_in_cm','courier_type'];
            $validAttributes = array_combine($validAttributes, $validAttributes);
            $additionalFields = array_diff_key($data, $validAttributes);
            if (count($additionalFields) > 0) {
                throw new LocalizedException(__('Some additional attributes are added in CSV. Please remove and try again. Additional attributes are: ' . implode(", ", array_keys($additionalFields))));
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

        if(!in_array('courier_type', $header) && in_array('package_weight_in_kg', $header) && (!isset($data['package_weight_in_kg']) || !trim($data['package_weight_in_kg']))) {
            $invalidFields[] = 'package_weight_in_kg';
            $isRawValid = false;
        }
        if(!in_array('courier_type', $header)){
            if(in_array('package_width_in_cm', $header) && (!isset($data['package_width_in_cm']) || !trim($data['package_width_in_cm']))) {
                $invalidFields[] = 'package_width_in_cm';
                $isRawValid = false;
            }

            if(in_array('package_length_in_cm', $header) && (!isset($data['package_length_in_cm']) || !trim($data['package_length_in_cm']))) {
                $invalidFields[] = 'package_length_in_cm';
                $isRawValid = false;
            }

            if(in_array('package_height_in_cm', $header) && (!isset($data['package_height_in_cm']) || !trim($data['package_height_in_cm']))) {
                $invalidFields[] = 'package_height_in_cm';
                $isRawValid = false;
            }
        }

        if(in_array('courier_type', $header) && (!isset($data['courier_type']) || !trim($data['courier_type']))) {
            $invalidFields[] = 'courier_type';
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
        $flag = true;
        foreach ($raws as $raw) {
            $this->writeLog('Import - Start sku => '.$raw['sku']);
            if(isset($raw['package_weight_in_kg'])){
                if((!is_numeric($raw['package_weight_in_kg'])) && (!is_float($raw['package_weight_in_kg']))){
                    throw new LocalizedException(__('package_weight_in_kg is not numeric and decimal '));
                }
            }
            if(isset($raw['package_height_in_cm'])){
                if((!is_numeric($raw['package_height_in_cm'])) && (!is_float($raw['package_height_in_cm']))){
                    throw new LocalizedException(__('package_height_in_cm is not numeric and decimal '));
                }
            }
            if(isset($raw['package_length_in_cm'])){
                if((!is_numeric($raw['package_length_in_cm'])) && (!is_float($raw['package_length_in_cm']))){
                    throw new LocalizedException(__('package_length_in_cm is not numeric and decimal '));
                }
            }
            if(isset($raw['package_width_in_cm'])){
                if((!is_numeric($raw['package_width_in_cm'])) && (!is_float($raw['package_width_in_cm']))){
                    throw new LocalizedException(__('package_width_in_cm is not numeric and decimal '));
                }
            }
            
            try {
                if(isset($raw['sku']) && $raw['sku'] !='') {
                    $entityVar = $raw['sku'];
                    $uniqueColumn = 'sku';
                    //$productObject = $this->productRepository->get($entityVar);
                    $productObject = $this->productFactory->create()->loadByAttribute('sku', $entityVar);
                }

                if (!$productObject) {
                    $raw['error'] = "Product doesn't exist with ".$uniqueColumn.":" . $entityVar;
                    $this->products['failure'][] = $raw;
                    continue;
                }
                $data = $raw;
                $error = [];
                if (!empty($data) && empty($error) && !isset($raw['error'])) {
                    unset($data['sku']);
                    $productId = $oldProductData = [];
                    $oldProductData[$productObject->getSku()] = [
                                         'package_length_in_cm' => $productObject->getPackageLengthInCm(),
                                         'package_height_in_cm' => $productObject->getPackageHeightInCm(),
                                         'package_width_in_cm'  => $productObject->getPackageWidthInCm(),
                                         'package_weight_in_kg' => $productObject->getPackageWeightInKg(),
                                         'courier_type' => $productObject->getCourierType()
                                        ];

                    $this->products['OldproductData'][] = $oldProductData;
                    $productId[] = $productObject->getId();
                    if(!array_key_exists('courier_type', $data)){
                        $categoryIds = $productObject->getCategoryIds();
                        $collection = $this->subclassCol->create();
                        if($collection->getSize() > 0 && count($categoryIds) > 0){
                            $products_subclassids = array_unique(array_column($collection->getData(), 'subclass_id'));
                            foreach ($categoryIds as $key => $catId) {
                                if(in_array($catId, $products_subclassids)){
                                    $flag = false;
                                    $this->writeLog("This SKU : " .$productObject->getSku() . ", is part of Exclude subclass list.");
                                }
                            }
                        }
                        $courierType = ($flag) ? $this->calculateCourierFlag($data['package_length_in_cm'],$data['package_height_in_cm'],$data['package_width_in_cm'],$data['package_weight_in_kg']): 'F';
                        $data['courier_type'] = $courierType;
                        $data['two_step_publish_cron'] = 0;
                    }else{
                        $courierType = $data['courier_type'];
                        $data = [];
                        $data['courier_type'] = $courierType;
                    }
                    $this->productAction->updateAttributes($productId, $data, 0);
                    $this->products['updatedProductData'][$productObject->getSku()][] = $data;
                    $this->productPushHelper->updateCatalogServicePushData($productObject->getId());
                    
                    //$this->products['success'][] = $raw;
                } else {
                    $raw['additional_error'] = "Invalid fields: ";
                    $raw['additional_error'] .= implode(',', $error);
                    $this->writeLog(print_r(['Skip row' => $raw],true));
                    $this->products['failure'][] = $raw;
                }

            } catch (\Exception $e) {
                $entityVal = $raw['sku'];
                $this->writeLog("There is some issue for : " .$entityVal . ", error=>" .$e->getMessage());
                $raw['error'] = $e->getMessage();
                $this->products['failure'][] = $raw;
            }
             $this->writeLog('Import - End sku => '.$raw['sku']);
        }
    }

    public function calculateCourierFlag($length,$height,$width,$weight)
    {
        $dimension = (($length * $height * $width) / 5000 * 1.2);
        $this->writeLog('Product dimension => '.$dimension);
        if($dimension < 10 && $weight < 10 && $length < 100 && $height < 100 && $width < 100)
        {
            $courierType = 'C';
            $this->writeLog('Product dimension  fulfil => '.$courierType);
        }elseif(($dimension >= 10 && $dimension < 200) && ($weight >= 10 && $weight < 200) && $length < 100 && $height < 100 && $width < 100)
        {
            $courierType = 'P';
            $this->writeLog('Product dimension fulfil => '.$courierType);
        }else {
            $courierType = 'F';
            $this->writeLog('Product dimension fulfil => '.$courierType);
        }

        return $courierType;
    }

    /* log for an Import data */
    public function writeLog($log)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/wms_productupdate.log');
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
            'Ibo_ProductUpdate::dimension'
        );
    }
}
