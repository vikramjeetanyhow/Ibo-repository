<?php

namespace Ibo\MultiSlider\Model\Data\HeroSliderData;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Ibo\MultiSlider\Model\Secondary\ResourceModel\ResourceHeroSliderData;
use Ibo\MultiSlider\Model\Secondary\Collection\CollectionHeroSliderData;
use Ibo\MultiSlider\Model\Primary\Collection\HeroSliderDataFactory;
use Ibo\MultiSlider\Model\Primary\ResourceModel\HeroResourceModel;
use Ibo\MultiSlider\Model\Secondary\SecondaryHeroSliderDataFactory;
use Ibo\MultiSlider\Model\Primary\Model\HeroSliderFactory;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;
use Ibo\MultiSlider\Helper\Adminhtml\Data;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DataProvider extends AbstractDataProvider implements DataProviderInterface
{
    protected $collectionHeroSlider;
    protected $heroResourceModel;
    protected $heroSliderModel;
    protected $foriegnResource;
    protected $primaryFieldName;
    protected $storeManager;
    protected $secondaryModel;
    protected $serializer;
    protected $loadedData;
    protected $collection;
    protected $helperData;
    protected $primaryId;
    protected $request;
    protected $logger;
    private $pool;
    /**
     * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @codingStandardsIgnoreStart
     */
    public function __construct(
        CollectionHeroSliderData $collectionHeroSlider,
        SecondaryHeroSliderDataFactory $secondaryModel,
        HeroSliderDataFactory $collectionFactory,
        ResourceHeroSliderData $foriegnResource,
        HeroResourceModel $heroResourceModel,
        StoreManagerInterface $storeManager,
        HeroSliderFactory $heroSliderModel,
        SerializerInterface $serializer,
        RequestInterface $request,
        PoolInterface $pool,
        $primaryFieldName,
        $requestFieldName,
        LoggerInterface $logger,
        Data $helperData,
        $name,
        array $meta = [],
        array $data = []
    ) {
        $this->collectionHeroSlider = $collectionHeroSlider;
        $this->collection = $collectionFactory->create();
        $this->heroResourceModel = $heroResourceModel;
        $this->primaryFieldName = $primaryFieldName;
        $this->foriegnResource = $foriegnResource;
        $this->heroSliderModel = $heroSliderModel;
        $this->storeManager = $storeManager;
        $this->secondaryModel = $secondaryModel;
        $this->serializer = $serializer;
        $this->helperData = $helperData;
        $this->request = $request;
        $this->pool = $pool;
        $this->logger = $logger;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }
    // @codingStandardsIgnoreEnd
    //To map data and the form fields
    public function getData()
    {
        $requestId = $this->request->getParam('id');
        $model = $this->heroSliderModel->create();
        $this->heroResourceModel->load($model, $requestId, 'id');
        $primaryData = $model->getData();
        $this->primaryId = $model->getId();
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }
        foreach ($primaryData as $key => $value) {
            if ($key !== 'slider_id') {
                $this->loadedData[$requestId][$key] = $value;
            }
        }
        $this->getDynamicRows();
        if ($this->loadedData != null) {
            $this->preparePopup();
        }
        return $this->loadedData;
    }

    public function getMeta()
    {
        $sliderId = $this->request->getParam('id');
        $meta = parent::getMeta();        
        return $meta;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getDynamicRows()
    {
        $secondaryModel = $this->secondaryModel->create();
        $this->foriegnResource->load($secondaryModel, $this->primaryId, 'slider_id');
        $item = $this->collectionHeroSlider->addFieldToFilter('slider_id', $this->primaryId)->getData();
        if (count($item)) {
            foreach ($item as $key => $value) {
                foreach ($value as $node => $val) {
                    if ($node !== "small_image" && $node !== "large_image") {
                        if ($node=='title') {
                            $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                            ['dynamic_rows_slider_information'][$key]['title_row'] = $val;
                        }
                        if ($node=='status') {
                            $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                            ['dynamic_rows_slider_information'][$key]['status_row'] = $val;
                        }
                        $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                        ['dynamic_rows_slider_information'][$key][$node] = $val;
                    } else {
                        $this->setImageData($node, $key, $val);
                    }
                }
            }
        }
    }
    /**
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function preparePopup()
    {
        $smallImage = [];
        $largeImage = [];
        $mediumImage = [];
        $extraLargeImage = [];
        if (isset($this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
            ['dynamic_rows_slider_information'])) {
            $items = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
            ['dynamic_rows_slider_information'];
            foreach ($items as $key => $value) {
                $json = "";
                $json = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                ['dynamic_rows_slider_information'][$key];

                $smallImage['name'] = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                ['dynamic_rows_slider_information'][$key]['small_image'][0]['name'];
                if ($smallImage['name']!=="") {
                    $smallImage['url'] = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                    ['dynamic_rows_slider_information'][$key]['small_image'][0]['url'];
                } else {
                    $smallImage['url'] = "";
                }

                $largeImage['name'] = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                ['dynamic_rows_slider_information'][$key]['large_image'][0]['name'];
                if ($largeImage['name']!=="") {
                    $largeImage['url'] = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                    ['dynamic_rows_slider_information'][$key]['large_image'][0]['url'];
                } else {
                    $largeImage['url'] = "";
                }  
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
                [$key]['json']['small_image'] = $smallImage;
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
                [$key]['json']['large_image'] = $largeImage;               

                foreach ($value as $keys => $vals) {
                    if ($keys !== "small_image" && $keys !== "large_image") {
                        $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                        ['dynamic_rows_slider_information']
                        [$key]['json'][$keys] = $vals;
                        $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                        ['dynamic_rows_slider_information']
                        [$key]['json']['record_id'] = $key;
                    }
                }
                $json = $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                ['dynamic_rows_slider_information'][$key]['json'];
                $json = $this->serializer->serialize($json);
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']
                ['dynamic_rows_slider_information'][$key]['json'] = $json;
            }

            return true;
        }
    }
    /**
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function setImageData($node, $key, $val)
    {
        if ($node == "small_image") {
            $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
            [$key]['small_image'][0]['name'] = $val;
            if ($val!=="") {
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
                [$key]['small_image'][0]['url'] = $this->helperData->getMediaUrl() . 'ibo/heroslider/small/' . $val;
            } else {
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
                [$key]['small_image'][0]['url'] = "";
            }
        }
        if ($node == "large_image") {
            $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
            [$key]['large_image'][0]['name'] = $val;
            if ($val!=="") {
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
                [$key]['large_image'][0]['url'] = $this->helperData->getMediaUrl() . 'ibo/heroslider/large/' . $val;
            } else {
                $this->loadedData[$this->primaryId]['data']['ibo_heroslider_form']['dynamic_rows_slider_information']
                [$key]['large_image'][0]['url'] = "";
            }
        }        
       
    }
}
