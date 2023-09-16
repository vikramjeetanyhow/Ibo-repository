<?php
namespace Ibo\MultiSlider\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns;
use Ibo\MultiSlider\Model\Secondary\Collection\CollectionHeroSliderDataFactory;

class SlidersCount extends Columns
{
    protected $collection;

    public function __construct(
        ContextInterface $context,
        CollectionHeroSliderDataFactory $collection,
        array $components = [],
        array $data = []
    ) {
        $this->collection = $collection;
        parent::__construct($context, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as $key => $items) {
                $arrayCount = $this->collection->create()->addFieldToFilter('slider_id', $items['id'])->getItems();
                $dataSource['data']['items'][$key]['no_of_banners'] = count($arrayCount);
            }
            /*if ($dataSource['data']['items']) {
                foreach ($dataSource['data']['items'] as $key => $items) {
                    $dataSource['data']['items'][$key]['show_in_home_page'] =
                        $items['show_in_home_page']==true?__("Yes"):__("No");
                }
            }*/
        }
        return $dataSource;
    }
}
