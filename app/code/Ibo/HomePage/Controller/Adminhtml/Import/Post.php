<?php
/**
 * Bestdeal Products Import
 *
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\HomePage\Controller\Adminhtml\Import;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Ibo\HomePage\Model\HomeBestdealFactory;
use Magento\Framework\File\Csv;


class Post extends \Ibo\HomePage\Controller\Adminhtml\Import
{
    /**
     * @var Csv
     */
    protected $csv;

    /**
     * @var HomeBestdeal
     */
    protected $homeBestdealFactory;


    /**
     * @param Csv $csv
     * @param HomeBestdeal $homeBestdealFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        Csv $csv,
        HomeBestdealFactory $homeBestdealFactory
    ) {
        parent::__construct($context,$fileFactory);
        $this->csv = $csv;
        $this->homeBestdealFactory = $homeBestdealFactory;
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
        $productImportFile = $this->getRequest()->getFiles('bestdealimport_file');
        if ($this->getRequest()->isPost() && isset($productImportFile['tmp_name'])) {
            try {

                $header = [];
                $importData = [];
                $pathinfo = pathinfo($productImportFile['name']);
                if (!isset($productImportFile['tmp_name'])) {
                    throw new LocalizedException(__('Invalid file upload attempt.'));
                }
                if (!in_array($pathinfo['extension'], ['csv'])) {
                    throw new LocalizedException(__('Please upload CSV file.'));
                }
                $csvData = $this->getCsvData($productImportFile['tmp_name']);
                if (empty($csvData)) {
                    throw new LocalizedException(__('File is empty'));
                }
                $homeBestdealModel = $this->homeBestdealFactory;
                $model = $homeBestdealModel->create();

                $homeBestdealData = [];
                foreach ($csvData as $rowIndex => $dataRow) {
                    //skip headers
                    if ($rowIndex == 0) {
                        $header = $dataRow;
                        continue;
                    }

                    $data = array_combine($header, $dataRow);

                    if(empty($data['sku']) || empty($data['customer_group']) || empty($data['from_date'])) {
                        throw new LocalizedException(__("sku, customer_group and from_date cann't be empty" ));
                    }
                    $homeBestdealData[] = $data;
                }

                foreach($homeBestdealData as $bestDeal){
                    if($bestDeal['id']==''){
                        unset($bestDeal['id']);
                    }
                    if(isset($bestDeal['is_delete']) && $bestDeal['is_delete']==''){
                        unset($bestDeal['is_delete']);
                    }

                    if(!empty($bestDeal['id']) && !empty($bestDeal['is_delete']) && $bestDeal['is_delete']==1){
                        $this->deleteBestDeal($bestDeal['id']);
                    } else {
                        $model->setData($bestDeal);
                        $model->save();
                    }
                }

                $this->messageManager->addSuccess(__('The products has been imported.'));

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
            'Ibo_Homepage::bestdeal'
        );
    }

    public function deleteBestDeal($id){
        $modelBestDeal = $this->homeBestdealFactory->create();
        $modelBestDeal->load($id);
        $modelBestDeal->delete();
    }
}
