<?php

namespace Ibo\MultiSlider\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Ibo\MultiSlider\Model\Primary\Collection\HeroSliderDataFactory;

class SliderLists implements OptionSourceInterface
{
    private $collectionFactory;

    public function __construct(
        HeroSliderDataFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    public function toOptionArray()
    {
        $sliders = [];
        $sliderCollections = $this->collectionFactory->create();
        $sliderCollections->addFieldToFilter('status',1);
        foreach ($sliderCollections as $key => $collection) {
            $collectionData = $collection->getData();
            $sliders[$key]['value'] = $collectionData['id'];
            $sliders[$key]['label'] = $collectionData['identifier'];
        }
        return $sliders;
    }
}
