<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Receipt;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Upload extends \Magento\Backend\App\Action
{
	protected $imageUploader;

	public function __construct(
		\Magento\Backend\App\Action\Context $context,
        \Magento\Catalog\Model\ImageUploader $imageUploader,
        Database $coreFileStorageDatabase,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
	) {		
		parent::__construct($context);
        $this->imageUploader = $imageUploader;
        $this->coreFileStorageDatabase = $coreFileStorageDatabase;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->uploaderFactory = $uploaderFactory;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
	}

	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::receipt_edit');
	}


	public function execute()
	{
		try {
			$result = $this->imageUploader->saveFileToTmpDir('header_logo');
			$result['cookie'] = [
				'name' => $this->_getSession()->getName(),
				'value' => $this->_getSession()->getSessionId(),
				'lifetime' => $this->_getSession()->getCookieLifetime(),
				'path' => $this->_getSession()->getCookiePath(),
				'domain' => $this->_getSession()->getCookieDomain(),
			];
		} catch (\Exception $e) {
			$result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
		}
		return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
	}
}