<?php
namespace Embitel\ProductImport\Controller\Adminhtml\Import;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;

class Download extends \Embitel\ProductImport\Controller\Adminhtml\Import
{
    public function __construct(
        Context $context,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory
    ) {
        $this->fileFactory = $fileFactory;
        parent::__construct($context, $fileFactory);
    }
 
    public function execute()
    {
        $fileName = $this->getRequest()->getParam('filename');
        $filepath = 'export/' . $fileName . '.csv';
        $downloadedFileName = $fileName.'.csv';
        $content['type'] = 'filename';
        $content['value'] = $filepath;
        $content['rm'] = 0;
        return $this->fileFactory->create($downloadedFileName, $content, DirectoryList::VAR_DIR);
    }
}
