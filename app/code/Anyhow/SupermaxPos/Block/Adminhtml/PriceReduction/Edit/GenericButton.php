<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Block\Adminhtml\PriceReduction\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Anyhow\SupermaxPos\Api\PriceReductionRepositoryInterface;

class GenericButton
{
    protected $context;
   
    protected $PriceReductionRepository;
    
    public function __construct(
        Context $context,
        PriceReductionRepositoryInterface $PriceReductionRepository
    ) {
        $this->context = $context;
        $this->PriceReductionRepository = $PriceReductionRepository;
    }

    public function getPriceReductionId()
    {
        try {
            return $this->PriceReductionRepository->getById(
                $this->context->getRequest()->getParam('id')
            )->getId();
        } catch (NoSuchEntityException $e) {
        }
        return null;
    }

    public function getUrl($route = '', $params = [])
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}