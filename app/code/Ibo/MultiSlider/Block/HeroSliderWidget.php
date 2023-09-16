<?php

namespace Ibo\MultiSlider\Block;

use Ibo\MultiSlider\Model\Secondary\Collection\CollectionHeroSliderData;
use Ibo\MultiSlider\Model\Primary\Collection\HeroSliderData;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Ibo\MultiSlider\Helper\Adminhtml\Data;
use Magento\Framework\View\Element\Template;
use Magento\Framework\UrlInterface;

/**
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class HeroSliderWidget extends Template implements BlockInterface
{
    protected $_template = "banner-widget-slider.phtml";
    protected $bannerType;
    protected $collectionHero;
    protected $storeManager;
    protected $collection;
    public $helperData;
    protected $model;

    const STATUS = 1;

    public function __construct(
        CollectionHeroSliderData $collectionHero,
        StoreManagerInterface $storeManager,
        HeroSliderData $model,
        Context $context,
        Data $helperData,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->collectionHero = $collectionHero;
        $this->storeManager = $storeManager;
        $this->model = $model;
        $this->helperData = $helperData;
    }

    public function getCollection()
    {
        $identifier = $this->getSliderIdentifier()!==null?$this->getSliderIdentifier():$this->getBannerIdentifier();
        $collection = $this->model->addFieldToFilter('status', self::STATUS)
            ->addFieldToFilter('identifier', $identifier)
            ->getItems();
        $id = [];
        if (!empty($collection)) {
            foreach ($collection as $item) {
                $id[] = $item->getId();
            }
            $this->collection = $this->collectionHero->addFieldToFilter('slider_id', $id)
                ->addFieldToFilter('status', self::STATUS)
                ->setOrder('sort_order', 'ASC')
                ->getItems();
            $this->getBreakpoints();
            return empty(!$this->collection)?$this->collection:false;
        }
        return false;
    }

    public function getIdentifier()
    {
        foreach ($this->collectionHero as $hero) {
            $identifier = $hero->getIdentifier();
        }
        return $identifier;
    }
    
    
    public function getBreakpoints()
    {
        $collection = $this->model
            ->addFieldToFilter('identifier', $this->getBannerIdentifier())
            ->addFieldToFilter('type')
            ->getItems();
        foreach ($collection as $value) {
            $this->bannerType = $value->getType();           
        }
    }
}
