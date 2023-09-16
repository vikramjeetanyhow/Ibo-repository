<?php

namespace Embitel\Importexportcategory\Controller\Adminhtml\Importcategory;

use Magento\Framework\App\Filesystem\DirectoryList;

class Edit extends \Magento\Backend\App\Action
{
    
    /**
     * Page factory
     *
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * Result JSON factory
     *
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * constructor
     *
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Backend\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Filesystem $fileSystem,
        \Magento\Framework\Filesystem\Io\File $fileio,
        \Magento\Backend\App\Action\Context $context
    ) {
    
        $this->_backendSession    = $context->getSession();
        $this->_resultPageFactory = $resultPageFactory;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_filesystem = $fileSystem;
        $this->_fileio = $fileio;
        parent::__construct($context);
    }

    /**
     * is action allowed
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Embitel_Importexportcategory::importexportcategory');
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Page|\Magento\Backend\Model\View\Result\Redirect|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page|\Magento\Framework\View\Result\Page $resultPage */
        $imagepath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath('catalog');
        $this->checkAndCreatePath($imagepath);
        $imagepath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath('catalog/category');
        $this->checkAndCreatePath($imagepath);
        $path = $this->_filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
        ->getAbsolutePath('categoryimport');
        $this->checkAndCreatePath($path);
        if (!is_writable($imagepath)) {
            $this->messageManager->addNotice(__('Please make this directory path writable pub/media/catalog/category'));
        }
        if (!is_writable($path)) {
            $this->messageManager->addNotice(__('Please make this directory path writable var/categoryimport'));
        }
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->setActiveMenu('Embitel_Importexportcategory::importexportcategory');
        $resultPage->getConfig()->getTitle()->prepend('Import Categories');
        return $resultPage;
    }

    /**
     * Check if path not exist then create it.
     * If path is not writable then update permission.
     *
     * @param type $path
     */
    protected function checkAndCreatePath($path)
    {
        try {
            if (!is_dir($path)) {
                $this->_fileio->mkdir($path, 0777, true);
                $this->_fileio->chmod($path, 0777, true);
            }
            if (!$this->_fileio->isWriteable($path)) {
                $this->_fileio->chmod($path, 0777, true);
            }
        } catch (\Exception $ex) {
        }
    }
}
