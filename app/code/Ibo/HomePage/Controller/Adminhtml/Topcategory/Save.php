<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\HomePage\Controller\Adminhtml\Topcategory;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\SerializerInterface;

class Save extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{

    protected $headers = [];
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Backend\Model\View\Result\ForwardFactory $resultForwardFactory,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Ibo\HomePage\Model\HomeCategoriesFactory $homeCategoryFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\File\Csv $csvParser,
        DirectoryList $directoryList,
        UploaderFactory $uploaderFactory,
        AdapterFactory $adapterFactory,
        Filesystem $filesystem,
        SerializerInterface $serializer,
        \Magento\Framework\Filesystem\Driver\File $file
    ) {
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context);
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultPageFactory = $resultPageFactory;
        $this->directoryList = $directoryList;
        $this->uploaderFactory = $uploaderFactory;
        $this->adapterFactory = $adapterFactory;
        $this->filesystem = $filesystem;
        $this->_file = $file;
        $this->csvParser = $csvParser;
        $this->serializer = $serializer;
        $this->homeCategoryFactory = $homeCategoryFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        if ($this->getRequest()->getPostValue()) {
            try {
                $data = $this->getRequest()->getPostValue();
                $categoryImportFile = $this->getRequest()->getFiles('ibo_import_file');
                $pathinfo = pathinfo($categoryImportFile['name']);

                if (!isset($categoryImportFile['tmp_name'])) {
                    throw new LocalizedException(__('Invalid file upload attempt.'));
                }
                if (!in_array($pathinfo['extension'], ['csv'])) {
                    throw new LocalizedException(__('Please upload CSV file.'));
                }
                $csvData = $this->getCsvData($categoryImportFile['tmp_name']);
                if (empty($csvData)) {
                    throw new LocalizedException(__('File is empty'));
                }

                $count = 0;
                $listSkus = [];

                $this->headers = array_values($csvData[0]);
                $data = [];
                foreach ($csvData as $key => $value) {
                    if ($key > 0) {
                        $catData = array_combine($this->headers,$value);
                        if(empty($catData['category_id']) || empty($catData['customer_group']) ||
                            empty($catData['type']) || empty($catData['from_date']) ||
                            empty($catData['display_zone'])) {

                            throw new LocalizedException(__("category_id, customer_group, type, from_date and display_zone can't be empty" ));
                        }
                        $data[] =$catData;
                    }
                }

                $homeCategoryModel = $this->homeCategoryFactory;
                foreach ($data as $homeCategoryData) {
                    $model = $homeCategoryModel->create();

                    if($homeCategoryData['id']==''){
                        unset($homeCategoryData['id']);
                    }
                    if(isset($homeCategoryData['is_delete']) && $homeCategoryData['is_delete']==''){
                        unset($homeCategoryData['is_delete']);
                    }

                    if(!empty($homeCategoryData['id']) && !empty($homeCategoryData['is_delete']) && $homeCategoryData['is_delete']==1){
                        $this->deleteTopCategory($homeCategoryData['id']);
                    } else {
                        $model->setData($homeCategoryData);
                        $model->save();
                    }
                }
                $this->messageManager->addSuccess(__('Your data was saved.'));
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('ibo_homepage/*/index');
                    return;
                }
                $this->_redirect('ibo_homepage/*/');
                return;
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
                $this->_redirect('ibo_homepage/*/index');
                return;
            } catch (\Exception $e) {
                $this->messageManager->addError(
                    __($e->getMessage().'Something went wrong while saving the item data. Please review the error log.')
                );
                $this->logger->critical($e);
                $this->_redirect('ibo_homepage/*/index');
                return;
            }
        }
        $this->_redirect('ibo_homepage/*/');
    }

    /**
     * Read CSV data
     *
     * @param type $fileName
     * @return type
     */
    public function getCsvData($fileName)
    {
        return $this->csvParser->getData($fileName);
    }

    public function deleteTopCategory($id){
        $modelHomeCat = $this->homeCategoryFactory->create();
        $modelHomeCat->load($id);
        $modelHomeCat->delete();
    }
}
