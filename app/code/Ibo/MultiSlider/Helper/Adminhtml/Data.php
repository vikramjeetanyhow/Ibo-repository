<?php

namespace Ibo\MultiSlider\Helper\Adminhtml;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Module\Manager;
use Vertex\Tax\Model\ModuleManager;

class Data extends AbstractHelper
{
    const XML_PATH_SMALL_IMAGE = 'image/hero_slider/image_dimentions_small';
    const XML_PATH_LARGE_IMAGE = 'image/hero_slider/image_dimentions_large';
    const XML_PATH_IMAGE_FILE_TYPES = 'image/hero_slider/image_file_type';
    
    protected $moduleManager;
    protected $storeManager;
    protected $scopeConfig;
    protected $serializer;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Json $serializer,
        Context $context,
        ModuleManager $moduleManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->serializer = $serializer;
        parent::__construct($context);
    }

    /**
     * Sample function returning config value
     * */
    public function getSmallImageProperties()
    {
        $smallImage = $this->scopeConfig
            ->getValue(self::XML_PATH_SMALL_IMAGE, ScopeInterface::SCOPE_STORE);
        $smallImage = $this->serializer->unserialize($smallImage);
        return array_shift($smallImage);
    }

    public function getLargeImageProperties()
    {
        $largeImage = $this->scopeConfig
            ->getValue(self::XML_PATH_LARGE_IMAGE, ScopeInterface::SCOPE_STORE);
        $largeImage = $this->serializer->unserialize($largeImage);
        return array_shift($largeImage);
    }

    public function getAllowedImageFileTypes()
    {
        $imageFileTypes = $this->scopeConfig
            ->getValue(self::XML_PATH_IMAGE_FILE_TYPES, ScopeInterface::SCOPE_STORE);
        return explode(',', $imageFileTypes);
    }

    public function getMediaUrl()
    {
        //Get the url for the image.
        $mediaUrl = $this->storeManager->getStore()
            ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        return $mediaUrl;
    }

    public function checkTarget($image)
    {
        $target = $image->getTarget() == true ? "_blank" : "_self";
        return $target;
    }
    /**
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function isModuleEnabled()
    {
        if ($this->moduleManager->isEnabled('Ibo_MultiSlider')) {
            return true;
        } else {
            return false;
        }
    }

    public function concatenateURL($url, $image)
    {
        return  $url . $image->getLink();
    }
}
