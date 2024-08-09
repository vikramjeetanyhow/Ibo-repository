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

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class ReceiptActions
 */
class SalesReportActions extends Column
{
    /** Url path */
    const CMS_URL_PATH_DETAIL = 'supermax/report/salesdetails';
    const CMS_URL_PATH_DOWNLOAD = 'supermax/report/salesdetailreportexport';
    const CMS_URL_PATH_SUMMARY = 'supermax/report/salessummary';
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var string
     */
    private $editUrl;
    private $downloadUrl;
    private $summaryUrl;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     * @param string $editUrl
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = [],
        $editUrl = self::CMS_URL_PATH_DETAIL,
        $downloadUrl = self::CMS_URL_PATH_DOWNLOAD,
        $summaryUrl = self::CMS_URL_PATH_SUMMARY
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->editUrl = $editUrl;
        $this->downloadUrl = $downloadUrl;
        $this->summaryUrl = $summaryUrl;
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
        if (isset($dataSource['data']['items'])) 
        {
            foreach ($dataSource['data']['items'] as & $item) 
            {
                $name = $this->getData('date_start');
                $title = $this->getEscaper()->escapeHtml($item['name']);
                if (isset($item['date_start'])) 
                {
                    $item[$this->getData('name')] = [
                        'download' => [
                            'href' => $this->urlBuilder->getUrl($this->downloadUrl, ['date_start' => $item['date_start'], 'date_end'=>$item['date_end'] ]),
                            'label' => __('Download Report')
                        ],
                        'view' => [
                            'href' => $this->urlBuilder->getUrl($this->summaryUrl, ['date_start' => $item['date_start'], 'date_end'=>$item['date_end'] ]),
                            'label' => __('Summary')
                        ],
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl($this->editUrl, ['date_start' => $item['date_start'], 'date_end'=>$item['date_end'] ]),
                            'label' => __('Details')
                        ]
                    ];
                }
            }
        }

        return $dataSource;
    }

    /**
     * Get instance of escaper
     * @return Escaper
     * @deprecated 101.0.7
     */
    private function getEscaper()
    {
        if (!$this->escaper) {
            $this->escaper = ObjectManager::getInstance()->get(Escaper::class);
        }
        return $this->escaper;
    }
}

