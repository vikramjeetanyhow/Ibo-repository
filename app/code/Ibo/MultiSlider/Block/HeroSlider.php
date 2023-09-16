<?php

namespace Ibo\MultiSlider\Block;

use Ibo\MultiSlider\Model\Secondary\Collection\CollectionHeroSliderData;
use Ibo\MultiSlider\Model\Primary\Collection\HeroSliderData;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\View\Element\Template;
use Ibo\MultiSlider\Helper\Adminhtml\Data;
use Magento\Framework\Module\Manager;
use Magento\Framework\UrlInterface;

/**
 * @SuppressWarnings(PHPMD.ShortVariable)
 */
class HeroSlider extends Template
{
    protected $assetRepository;
    protected $collectionHero;
    protected $storeManager;
    protected $bannerType;    
    public $helperData;
    protected $collection;
    protected $model;

    const STATUS = 1;

    public function __construct(
        Data $helperData,
        CollectionHeroSliderData $collectionHero,
        StoreManagerInterface $storeManager,
        HeroSliderData $model,
        Context $context
    ) {
        $this->collectionHero = $collectionHero;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
        $this->model = $model;
        parent::__construct($context);
    }

    public function getCollection()
    {
        //Add status and sort order for the image to display in front end
        $collection = $this->model->
        addFieldToFilter('status', self::STATUS)->
        addFieldToFilter('show_in_home_page', 1)->
        getItems();
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
