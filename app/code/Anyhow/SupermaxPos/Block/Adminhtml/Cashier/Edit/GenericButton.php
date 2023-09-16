<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Block\Adminhtml\Cashier\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Anyhow\SupermaxPos\Api\CashierRepositoryInterface;

class GenericButton
{
    protected $context;
   
    protected $CashierRepository;
    
    public function __construct(
        Context $context,
        CashierRepositoryInterface $CashierRepository
    ) {
        $this->context = $context;
        $this->CashierRepository = $CashierRepository;
    }

    public function getCashierId()
    {
        try {
            return $this->CashierRepository->getById(
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