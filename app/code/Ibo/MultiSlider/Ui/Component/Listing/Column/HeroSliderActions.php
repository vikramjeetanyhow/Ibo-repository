<?php

namespace Ibo\MultiSlider\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

class HeroSliderActions extends Column
{

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context, $uiComponentFactory, $components, $data);
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
            foreach ($dataSource['data']['items'] as &$item) {
                //To set the path for edit action
                $item[$this->getData('name')]['edit'] = [
                    'href' => $this->urlBuilder->getUrl(
                        'multislider/heroslider/edit',
                        ['id' => $item['id']]
                    ),
                    'label' => __('Edit'),
                    'hidden' => false,
                ];
                //To set the path for delete action
                $item[$this->getData('name')]['delete'] = [
                    'href' => $this->urlBuilder->getUrl(
                        'multislider/heroslider/delete',
                        ['id' => $item['id']]
                    ),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete %1', $item['identifier']),
                        'message' => __('Are you sure you want to delete a %1 record?', $item['identifier']),
                    ],
                    'post' => true,
                    'hidden' => false,
                ];
            }
        }
        return $dataSource;
    }
}
