<?php

namespace Ibo\MultiSlider\Controller\Adminhtml\HeroSlider;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;
use Ibo\MultiSlider\Helper\Adminhtml\Data;
use Ibo\MultiSlider\Model\ImageUploader;

class Upload extends Action
{
    protected $imageUploader;
    protected $resultFactory;
    protected $storeManager;
    protected $helperData;
    protected $fileDriver;
    protected $data;

    public function __construct(
        StoreManagerInterface $storeManager,
        ResultFactory $resultFactory,
        ImageUploader $imageUploader,
        Context $context,
        File $fileDriver,
        Data $helperData
    ) {
        $this->resultFactory = $resultFactory;
        $this->imageUploader = $imageUploader;
        $this->storeManager = $storeManager;
        $this->fileDriver = $fileDriver;
        $this->helperData = $helperData;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->setImages();
        $this->data = $data;
        try {
            //Images are saved when conditions are satisfied.
            if (isset($data['upload']['tmp_name']['small_image'])) {
                $this->checkFileType($data['upload']['type']['small_image']);
                $this->imageUploader->setAllowedExtensions($this->helperData->getAllowedImageFileTypes());
                
                $this->imageUploader->setBaseTmpPath('ibo/heroslider/small');
                $result = $this->imageUploader->saveFileToTmpDir('small_image');
            } elseif (isset($data['upload']['tmp_name']['large_image'])) {
                $this->checkFileType($data['upload']['type']['large_image']);
                $this->imageUploader->setAllowedExtensions($this->helperData->getAllowedImageFileTypes());
               
                $this->imageUploader->setBaseTmpPath('ibo/heroslider/large');
                $result = $this->imageUploader->saveFileToTmpDir('large_image');
            } 
        } catch (Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }

    public function checkFileType($fileType)
    {
        if (substr($fileType, 0, 5) == 'image') {
            return true;
        }
        /** phpcs:ignore */
        throw new LocalizedException(new Phrase((__("Please upload images only."))));
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function setImages()
    {
        $smallimage = $this->getRequest()->getFiles('small_image');        
        $largeImage = $this->getRequest()->getFiles('large_image');        
        $newUpload = [];
        if (isset($smallimage)) {
            $newUpload['upload']['name']['small_image'] = $smallimage['name'];
            $newUpload['upload']['type']['small_image'] = $smallimage['type'];
            $newUpload['upload']['tmp_name']['small_image'] = $smallimage['tmp_name'];
            $newUpload['upload']['error']['small_image'] = $smallimage['error'];
            $newUpload['upload']['size']['small_image'] = $smallimage['size'];
        } elseif (isset($largeImage)) {
            $newUpload['upload'] = [];
            $newUpload['upload']['name']['large_image'] = $largeImage['name'];
            $newUpload['upload']['type']['large_image'] = $largeImage['type'];
            $newUpload['upload']['tmp_name']['large_image'] = $largeImage['tmp_name'];
            $newUpload['upload']['error']['large_image'] = $largeImage['error'];
            $newUpload['upload']['size']['large_image'] = $largeImage['size'];
        }
        return $newUpload;
    }
}
