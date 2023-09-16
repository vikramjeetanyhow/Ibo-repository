<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

// Return Barcode type as 'Generated' or 'Added' on the basis of the database value as 1 and 2 to anyhow_supetrmax_products file.
namespace Anyhow\SupermaxPos\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class BarcodeType extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
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
        if(isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach($dataSource['data']['items'] as & $item) {
                $ahBarcodeBasedon = '';
                if($item[$fieldName]== 1) {
                    $ahBarcodeBasedon = 'Generated';
                } elseif($item[$fieldName]== 2) {
                    $ahBarcodeBasedon = 'Added';
                }
                $item[$fieldName] = $ahBarcodeBasedon;
            }
        }
        return $dataSource;
    }
}