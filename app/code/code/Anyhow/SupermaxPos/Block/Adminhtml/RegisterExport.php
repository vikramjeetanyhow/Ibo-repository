<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Block\Adminhtml;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class RegisterExport implements ButtonProviderInterface
{
    /**
     * Url Builder
     *
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * CustomButton constructor.
     *
     * @param \Magento\Backend\Block\Widget\Context $context
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Backend\Block\Widget\Context $context
    ) {
        $this->request = $request;
        $this->urlBuilder = $context->getUrlBuilder();
    }
    /**
     * @return array
     */
    public function getButtonData()
    {
        $registerId = $this->request->getParam('pos_register_id');
        $data = [
            'label' => __('Export Register Details'),
            'class' => 'primary',
            'id' => 'custom-button',
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('supermax/register/exportdetaildata', ['pos_register_id' => $registerId])),
            'sort_order' => 20,
        ];
        return $data;
    }

    /**
     * Generate url by route and parameters
     *
     * @param   string $route
     * @param   array $params
     * @return  string
     */
    public function getUrl($route = '', $params = [])
    {
        return $this->urlBuilder->getUrl($route, $params);
    }
}