<?php

namespace Embitel\ProductExport\Ui\Component\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\ImportExport\Controller\Adminhtml\Export\File\Download;
use Magento\Ui\Component\Listing\Columns\Column;

class PageActions extends Column
{

    const URL = 'embproductexport/export/download/';
    private UrlInterface $urlBuilder;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface   $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface       $urlBuilder,
        array              $components = [],
        array              $data = []
    )
    {
        $this->urlBuilder = $urlBuilder;
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $name = $this->getData('name');
                if ($item['status'] == "success") {
                    $item[$name]["view"] = [
                        "href" => $this->urlBuilder->getUrl(
                            self::URL,
                            ['_query' => ['filename' => $item['filename']]]
                        ),
                        "label" => __("Download")
                    ];
                }

            }
        }

        return $dataSource;
    }
}