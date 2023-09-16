<?php
/**
 * @category   IBO
 * @package    Ibo_ProductUpdate
 * @author Hitendra Badiani <hitendra.badiani@embitel.com>
 */
namespace Ibo\ProductUpdate\Controller\Adminhtml\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;


class Export extends \Ibo\ProductUpdate\Controller\Adminhtml\Import
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
     * @var \Ibo\ProductUpdate\Model\SubclassFactory
     */
    protected $subclassFactory;
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
        \Ibo\ProductUpdate\Model\SubclassFactory $subclassFactory,
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList
    ) {
        $this->fileFactory = $fileFactory;
        $this->resultLayoutFactory = $resultLayoutFactory;
        $this->csvProcessor = $csvProcessor;
        $this->directoryList = $directoryList;
        $this->subclassFactory = $subclassFactory;
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
            'subclass_id' => __('subclass_id'),
            'subclass_name' => __('subclass_name'),
        ];
        $resultLayout = $this->resultLayoutFactory->create();
        $collection = $this->subclassFactory->create()->getCollection();
        $fileName = 'exclude_subclass_list.csv'; // Add Your CSV File name
        $filePath =  $this->directoryList->getPath(DirectoryList::MEDIA) . "/" . $fileName;

        if($collection->getSize()>0){
          foreach ($collection as $subclassData) {
            $content[] = [
                $subclassData->getSubclassId(),
                $subclassData->getSubclassName()
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
