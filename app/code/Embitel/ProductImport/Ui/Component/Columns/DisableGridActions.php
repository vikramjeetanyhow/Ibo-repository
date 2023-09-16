<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductImport\Ui\Component\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Embitel\ProductImport\Controller\Adminhtml\EboDisable\File\Download;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

/**
 * Actions for export grid.
 */
class DisableGridActions extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * ExportGridActions constructor.
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
            foreach ($dataSource['data']['items'] as & $item) {
                $name = $this->getData('name');
                if (isset($item['success_filename']) || isset($item['failure_filename'])) {
                    $item[$name]['view'] = [
                        'href' => $this->urlBuilder->getUrl(Download::URL, ['success_filename' => $item['success_filename'],'failure_filename' => $item['failure_filename']]),
                        'label' => __('Download')
                    ];                   
                }
            }
        }
        return $dataSource;
    }
}
