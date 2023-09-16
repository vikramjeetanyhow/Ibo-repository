<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Block\Adminhtml\CashierRole\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Anyhow\SupermaxPos\Api\CashierRoleRepositoryInterface;

class GenericButton
{
    protected $context;
   
    protected $CashierRoleRepository;
    
    public function __construct(
        Context $context,
        CashierRoleRepositoryInterface $CashierRoleRepository
    ) {
        $this->context = $context;
        $this->CashierRoleRepository = $CashierRoleRepository;
    }

    public function getCashierRoleId()
    {
        try {
            return $this->CashierRoleRepository->getById(
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