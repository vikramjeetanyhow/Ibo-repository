<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Ui\Component\Listing\Columns;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class SupermaxPosOrderAction extends Column
{
    
    protected $urlBuilder;
    const CMS_URL_PATH_INVOICE = 'supermax/order/invoice';
    const PAYMENT_INTENT_GENERATE = 'supermax/order/generate';
    const CMS_URL_PATH_DELETE_DUPLICATE = 'supermax/order/deleteduplicate';

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = [],
        $invoiceUrl = self::CMS_URL_PATH_INVOICE,
        $generatePaymentIntentUrl = self::PAYMENT_INTENT_GENERATE,
        $deleteDuplicateUrl = self::CMS_URL_PATH_DELETE_DUPLICATE
        
    ) {
        $this->helper = $helper;
        $this->urlBuilder = $urlBuilder;
        $this->invoiceUrl = $invoiceUrl;
        $this->generatePaymentIntentUrl = $generatePaymentIntentUrl;
        $this->deleteDuplicateUrl = $deleteDuplicateUrl;
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
                if (isset($item['order_id'])) {
                    // $orderId = $item['increment_id'];
                    
                    // $orderId = 100053370;
                    $viewUrlPath = $this->getData('config/viewUrlPath') ?: '#';
                    $urlEntityParamName = $this->getData('config/urlEntityParamName') ? : 'order_id';
                    $item[$this->getData('name')] = [
                        'view' => [
                            'href' => $this->urlBuilder->getUrl(
                                $viewUrlPath,
                                [
                                    $urlEntityParamName => $item['order_id']
                                ]
                            ),
                            'label' => __('View')
                        ],
                        'invoice' => [
                            'href' => $this->urlBuilder->getUrl(
                                $this->invoiceUrl,
                                [
                                    'order_id' =>$item['increment_id']
                                ]
                            ),                             
                            'target' => '_blank',
                            'label' => __('Print Invoice')
                            
                        ],
                        'generate_paymentintent' => [
                            'href' => $this->urlBuilder->getUrl(
                                $this->generatePaymentIntentUrl,
                                [
                                    'order_id' => $item['order_id']
                                ]
                            ),                             
                            'label' => __('Generate Payment Intent')
                        ]
                    ];

                    $isDuplicateItems = $this->helper->getOrderDuplicateItems($item['order_id']);
                    if(!empty($isDuplicateItems)) {
                        $item[$this->getData('name')]['delete_duplicate'] = [
                            'href' => $this->urlBuilder->getUrl(
                                $this->deleteDuplicateUrl,
                                [
                                    'order_id' => $item['order_id']
                                ]
                            ),                             
                            'label' => __('Delete Duplicate & Repush')
                        ];
                    }
                }
            }
        }

        return $dataSource;
    }
}
