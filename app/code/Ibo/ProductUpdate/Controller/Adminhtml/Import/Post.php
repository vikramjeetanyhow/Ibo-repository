<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Controller\Adminhtml\Import;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Ibo\ProductUpdate\Model\SubclassFactory;
use Ibo\ProductUpdate\Model\ResourceModel\Subclass\CollectionFactory;
use Magento\Framework\File\Csv;


class Post extends \Ibo\ProductUpdate\Controller\Adminhtml\Import
{   
    /**
     * @var Csv
     */
    protected $csv;

    /**
     * @var HomeBestdeal
     */
    protected $subclassFactory;

    
    /**
     * @param Csv $csv
     * @param HomeBestdeal $homeBestdealFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        Csv $csv,
        CollectionFactory $subclassCollection,
        SubclassFactory $subclassFactory        
    ) {
        parent::__construct($context,$fileFactory);
        $this->csv = $csv;
        $this->subclassFactory = $subclassFactory;
        $this->subclassCol = $subclassCollection;
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
        $subclassImportFile = $this->getRequest()->getFiles('subclassimport_file');
        if ($this->getRequest()->isPost() && isset($subclassImportFile['tmp_name'])) {
            try {

                $header = [];
                $importData = [];
                $pathinfo = pathinfo($subclassImportFile['name']);
                if (!isset($subclassImportFile['tmp_name'])) {
                    throw new LocalizedException(__('Invalid file upload attempt.'));
                }
                if (!in_array($pathinfo['extension'], ['csv'])) {
                    throw new LocalizedException(__('Please upload CSV file.'));
                }
                $csvData = $this->getCsvData($subclassImportFile['tmp_name']);
                if (empty($csvData)) {
                    throw new LocalizedException(__('File is empty'));
                }               
                $collection = $this->subclassCol->create();
                $collection->walk('delete');
                $subclassModel = $this->subclassFactory;
                $model = $subclassModel->create();                
                foreach ($csvData as $rowIndex => $dataRow) {
                    
                    $subclassData = [];
                    //skip headers
                    if ($rowIndex == 0 && in_array('subclass_id',$dataRow)) {
                        $header = $dataRow;
                        continue;
                    }else if($rowIndex == 0 && !in_array('subclass_id',$dataRow)){
                        throw new LocalizedException(__('Header subclass_id field missing.'));
                    }else if($dataRow[0]==''){
                        continue;
                    }
                    $subclassData = array_combine($header, $dataRow);                                      
                    $model->setData($subclassData);              
                    $model->save();
                }
                $this->messageManager->addSuccess(__('The exclude subclass id list has been imported.'));
                
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
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(
            'Ibo_ProductUpdate::import'
        );
    }
}
