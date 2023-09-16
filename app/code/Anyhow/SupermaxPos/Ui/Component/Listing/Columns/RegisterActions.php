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
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class ReceiptActions
 */
class RegisterActions extends Column
{
    /** Url path */
    const CMS_URL_PATH_DETAIL = 'supermax/register/details';
    const CMS_URL_PATH_EDIT = 'supermax/register/edit';
    const CMS_URL_PATH_PRINT = 'supermax/register/cashiereconcile';

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var string
     */
    private $editUrl;

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
        $editUrl = self::CMS_URL_PATH_DETAIL
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->editUrl = $editUrl;
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
               $name = $this->getData('pos_register_id');
                $title = $this->getEscaper()->escapeHtml($item['name']);
                if (isset($item['pos_register_id']) && $item['reconcile_status'] == '1') {
                    $item[$this->getData('name')] = [
                        'edits' => [
                            'href' => $this->urlBuilder->getUrl($this->editUrl, ['pos_register_id' => $item['pos_register_id']]),
                            'label' => __('Details'),
                        ],
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(self::CMS_URL_PATH_EDIT, ['pos_register_id' => $item['pos_register_id']]),
                            'label' => __('Edit'),
                        ],
                        'print' => [
                            'href' => $this->urlBuilder->getUrl(self::CMS_URL_PATH_PRINT, ['pos_register_id' => $item['pos_register_id']]),
                            'label' => __('Export'),
                        ],

                    ];
                } else if (isset($item['pos_register_id'])) {
                    $item[$this->getData('name')] = [
                        'edits' => [
                            'href' => $this->urlBuilder->getUrl($this->editUrl, ['pos_register_id' => $item['pos_register_id']]),
                            'label' => __('Details'),
                        ],
                        'edit' => [
                            'href' => $this->urlBuilder->getUrl(self::CMS_URL_PATH_EDIT, ['pos_register_id' => $item['pos_register_id']]),
                            'label' => __('Edit'),
                        ],
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
