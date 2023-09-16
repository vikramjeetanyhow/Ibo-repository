<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */


namespace Anyhow\SupermaxPos\Model;

use Anyhow\SupermaxPos\Api\CrosPosInterface;

class CrosPos implements CrosPosInterface
{
     public function __construct(
        \Magento\Framework\Webapi\Rest\Response $response,
        \Magento\Framework\Webapi\Rest\Request $request
    ) {
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * {@inheritDoc}
     */
    public function pos()
    {
        $this->response->setHeader('Access-Control-Allow-Methods', $this->request->getHeader('Access-Control-Request-Method'), true);
        $this->response->setHeader('Access-Control-Allow-Headers', $this->request->getHeader('Access-Control-Request-Headers'), true);
        return '';
    }

}
