<?php

namespace Ibo\MultiSlider\Model;

use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;
use Exception;

class ImageUploader
{
    public $allowedExtensions;
    private $coreFileStorage;
    private $uploaderFactory;
    private $mediaDirectory;
    private $storeManager;
    public $baseTmpPath;
    public $basePath;
    private $logger;

    public function __construct(
        StoreManagerInterface $storeManager,
        UploaderFactory $uploaderFactory,
        Database $coreFileStorage,
        LoggerInterface $logger,
        Filesystem $filesystem
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->uploaderFactory = $uploaderFactory;
        $this->coreFileStorage = $coreFileStorage;
        $this->storeManager = $storeManager;
        $this->basePath = 'ibo/heroslider';
        $this->logger = $logger;
    }

    public function setBaseTmpPath($baseTmpPath)
    {
        $this->baseTmpPath = $baseTmpPath;
    }

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function setAllowedExtensions($allowedExtensions)
    {
        $this->allowedExtensions = $allowedExtensions;
    }

    public function getBaseTmpPath()
    {
        return $this->baseTmpPath;
    }

    public function getBasePath()
    {
        return $this->basePath;
    }

    public function getAllowedExtensions()
    {
        return $this->allowedExtensions;
    }

    public function getFilePath($path, $imageName)
    {
        return rtrim($path, '/') . '/' . ltrim($imageName, '/');
    }

    public function moveFileFromTmp($imageName)
    {
        $baseTmpPath = $this->getBaseTmpPath();
        $basePath = $this->getBasePath();
        $baseImagePath = $this->getFilePath($basePath, $imageName);
        $baseTmpImagePath = $this->getFilePath($baseTmpPath, $imageName);
        try {
            $this->coreFileStorage->copyFile(
                $baseTmpImagePath,
                $baseImagePath
            );
            $this->mediaDirectory->renameFile(
                $baseTmpImagePath,
                $baseImagePath
            );
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Something went wrong while saving the file(s).')
            );
        }
        return $imageName;
    }

    public function saveFileToTmpDir($fileId)
    {
        $baseTmpPath = $this->getBaseTmpPath();
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions($this->getAllowedExtensions());
        $uploader->setAllowRenameFiles(true);
        $result = $uploader->save($this->mediaDirectory->getAbsolutePath($baseTmpPath));
        if (!$result) {
            throw new LocalizedException(
                __('File can not be saved to the destination folder.')
            );
        }

        $result['tmp_name'] = str_replace('\\', '/', $result['tmp_name']);
        $result['path'] = str_replace('\\', '/', $result['path']);
        $result['url'] = $this->storeManager
                ->getStore()
                ->getBaseUrl(
                    UrlInterface::URL_TYPE_MEDIA
                ) . $this->getFilePath($baseTmpPath, $result['file']);
        $result['name'] = $result['file'];
        if (isset($result['file'])) {
            try {
                $relativePath = rtrim($baseTmpPath, '/') . '/' . ltrim($result['file'], '/');
                $this->coreFileStorage->saveFile($relativePath);
            } catch (Exception $e) {
                $this->logger->critical($e);
                throw new LocalizedException(
                    __('Something went wrong while saving the file(s).')
                );
            }
        }
        return $result;
    }
}
