<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

// Display the actions on the product index UI.
namespace Anyhow\SupermaxPos\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\UrlInterface;

/**
 * Class ProductActions
 * @api
 * @since 100.0.2
 */
class ProductActions extends Column
{
    const CMS_URL_PATH_PRINT = 'supermax/barcode/printbarcode';
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
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $storeId = $this->context->getFilterParam('store_id');
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = [
                    'generate'=>[
                    'href' => $this->urlBuilder->getUrl(
                        'supermax/barcode/generate',
                        ['id' => $item['entity_id'], 'store' => $storeId]
                    ),
                    'label' => __('Generate Barcode'),
                    'hidden' => false,
                    '__disableTmpl' => true
                ],
                'print' => [
                    'href' => $this->urlBuilder->getUrl(self::CMS_URL_PATH_PRINT, ['entity_id' => $item['entity_id']]),
                    'label' => __('Print Barcode'),
                ]
                ];
            }
        }
        return $dataSource;
    }
}
