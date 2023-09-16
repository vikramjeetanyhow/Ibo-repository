<?php

namespace Ibo\MultiSlider\Block\Adminhtml\HeroSlider\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Registry;

class GenericButton
{

    protected $context;
    protected $coreRegistry;

    public function __construct(
        Registry $registry,
        Context $context
    ) {
        $this->coreRegistry = $registry;
        $this->context = $context;
    }

    public function getId()
    {
        $model = $this->coreRegistry->registry('hero_slider');
        if (isset($model) && $model->getId()) {
            return $model->getId();
        }
        return false;
    }

    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
