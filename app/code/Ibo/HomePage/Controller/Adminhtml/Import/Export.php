<?php
/**
 * Bestdeal Products Export
 *
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\HomePage\Controller\Adminhtml\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;


class Export extends \Ibo\HomePage\Controller\Adminhtml\Import
{
    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

    protected $customerGroupList = [];

    /**
     * @var \Magento\Framework\View\Result\LayoutFactory
     */
    protected $resultLayoutFactory;
    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csvProcessor;
    /**
     * @var \Magento\Framework\App\Filesystem\DirectoryList
     */
    protected $directoryList;

    /**
     * @var \Ibo\HomePage\Model\HomeBestdealFactory
     */
    protected $homeBestdealFactory;
    /**
     * @param \Magento\Framework\App\Action\Context            $context
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Magento\Framework\View\Result\LayoutFactory     $resultLayoutFactory
     * @param \Magento\Framework\File\Csv                      $csvProcessor
     * @param \Magento\Framework\App\Filesystem\DirectoryList  $directoryList
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        \Magento\Framework\View\Result\LayoutFactory $resultLayoutFactory,
        \Magento\Framework\File\Csv $csvProcessor,
        \Magento\Customer\Model\ResourceModel\Group\CollectionFactory $groupCollectionFactory,
        \Ibo\HomePage\Model\HomeBestdealFactory $homeBestdealFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        $this->fileFactory = $fileFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->csvProcessor = $csvProcessor;
        $this->directoryList = $directoryList;
        $this->homeBestdealFactory = $homeBestdealFactory;
        $this->_groupCollectionFactory = $groupCollectionFactory;
        parent::__construct($context,$fileFactory);
    }
    /**
     * CSV Create and Download
     *
     * @return ResponseInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function execute()
    {
        /** Add yout header name here */
        $content[] = [
            'id' => __('id'),
            'sku' => __('sku'),
            'customer_group' => __('customer_group'),
            'from_date' => __('from_date'),
            'to_date' => __('to_date'),
            'is_delete' => __('is_delete')

        ];
        $resultLayout = $this->resultLayoutFactory->create();
        $collection = $this->homeBestdealFactory->create()->getCollection();
        $fileName = 'home_bestdeals.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;
        $updateCustomerGroup = "";
        if($collection->getSize()>0){
          foreach ($collection as $bestdealData) {
            $updateCustomerGroup = $this->getCustomerGroupCodes($bestdealData->getCustomerGroup());
            $content[] = [
                $bestdealData->getId(),
                $bestdealData->getSku(),
                $updateCustomerGroup,
                $bestdealData->getFromDate(),
                $bestdealData->getToDate()

            ];
          }
        }

        $this->csvProcessor->setEnclosure('"')->setDelimiter(',')->saveData($filePath, $content);
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => "filename",
                'value' => $fileName,
                'rm'    => true, // True => File will be remove from directory after download.
            ],
            DirectoryList::MEDIA,
            'text/csv',
            null
        );
    }

    public function getCustomerGroupCodes($custGroup){

      if(count($this->customerGroupList) == 0){
        $customerGroup = $this->_groupCollectionFactory->create();
        $customerGroups = $customerGroup->getData();
        $code = array_column($customerGroups, 'customer_group_code');
        $ids = array_column($customerGroups, 'customer_group_id');
        $this->customerGroupList = array_combine($ids, $code);
      }

      if($custGroup != '' && is_numeric($custGroup) && in_array($custGroup, array_keys($this->customerGroupList))){
        $custGroup = $this->customerGroupList[$custGroup];
      }
      return $custGroup;
    }
}
