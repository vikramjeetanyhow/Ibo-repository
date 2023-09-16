<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Ibo\HomePage\Controller\Adminhtml\Topcategory;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;

class Export extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{

    /**
     * @var \Magento\Framework\App\Response\Http\FileFactory
     */
    protected $fileFactory;

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
     * @var \Ibo\HomePage\Model\HomeCategoriesFactory
     */
    protected $homeCategoryFactory;
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
        \Ibo\HomePage\Model\HomeCategoriesFactory $homeCategoryFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        $this->fileFactory = $fileFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->csvProcessor = $csvProcessor;
        $this->directoryList = $directoryList;
        $this->homeCategoryFactory = $homeCategoryFactory;
        parent::__construct($context);
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
            'category_id' => __('category_id'),
            'customer_group' => __('customer_group'),
            'type' => __('type'),
            'from_date' => __('from_date'),
            'to_date' => __('to_date'),
            'display_zone' => __('display_zone'),
            'is_delete' => __('is_delete'),

        ];
        $resultLayout = $this->resultLayoutFactory->create();
        $collection = $this->homeCategoryFactory->create()->getCollection();
        $fileName = 'home_top_categories.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;

        if($collection->getSize()>0){
          foreach ($collection as $categoryData) {
            $content[] = [
                $categoryData->getId(),
                $categoryData->getCategoryId(),
                $categoryData->getCustomerGroup(),
                $categoryData->getType(),
                $categoryData->getFromDate(),
                $categoryData->getToDate(),
                $categoryData->getDisplayZone()

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
}
