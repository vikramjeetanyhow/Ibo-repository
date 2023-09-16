<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Ui\Component\Listing\Column;

use Magento\Catalog\Helper\Image;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Thumbnail extends \Magento\Ui\Component\Listing\Columns\Column
{
    const NAME = 'mobile_image';
    const ALT_FIELD = 'name';
    protected $storeManager;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        Image $imageHelper,
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->storeManager = $storeManager;
        $this->imageHelper = $imageHelper;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            $path = $this->storeManager->getStore()->getBaseUrl(
                        \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                    );
            //$path = trim($this->_scopeConfig->getValue("core_media/service/get_media_api"));
            foreach ($dataSource['data']['items'] as & $item) {
                if ($item['mobile_image']) {
                    $item[$fieldName . '_src'] = $path.$item['mobile_image'];
                    $item[$fieldName . '_alt'] = $item['title'];
                    $item[$fieldName . '_orig_src'] = $path.$item['mobile_image'];
                }else{
                    $item[$fieldName . '_src'] = $this->imageHelper->getDefaultPlaceholderUrl('image');
                    $item[$fieldName . '_alt'] = 'Place Holder';
                    $item[$fieldName . '_orig_src'] = $this->imageHelper->getDefaultPlaceholderUrl('image');
                }
            }
        }

        return $dataSource;
    }
}