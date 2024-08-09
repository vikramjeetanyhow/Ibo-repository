<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Plugin;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Webapi\Controller\Rest\Router;

class CrosRestRequestPlugin
{
    private $request;
    protected $routeFactory;
    public function __construct(
        \Magento\Framework\Webapi\Rest\Request $request,
        \Magento\Framework\Controller\Router\Route\Factory $routeFactory
    ) {
        $this->request = $request;
        $this->routeFactory = $routeFactory;
    }

    /**
     * @param \Magento\Webapi\Model\Rest\Config $subject
     * @param $proceed
     * @param Request $request
     * @return \Magento\Webapi\Controller\Rest\Router\Route
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function aroundMatch(
        Router $subject,
        callable $proceed,
        Request $request
    )
    {
        try {
            $returnValue = $proceed($request);
        } catch (\Magento\Framework\Webapi\Exception $e) {
            $requestHttpMethod = $this->request->getHttpMethod();
            if ($requestHttpMethod == 'OPTIONS') {
                return $this->createRoute();
            } else {
                throw $e;
            }
        }
        return $returnValue;
    }

     /**
     * @return \Magento\Webapi\Controller\Rest\Router\Route
     */
    protected function createRoute()
    {
        /** @var $route \Magento\Webapi\Controller\Rest\Router\Route */
        $route = $this->routeFactory->createRoute(
            'Magento\Webapi\Controller\Rest\Router\Route',
            '/V1/cros/pos'
        );

        $route->setServiceClass('Anyhow\SupermaxPos\Api\CrosPosInterface')
            ->setServiceMethod('pos')
            ->setSecure(false)
            ->setAclResources(['anonymous'])
            ->setParameters([]);

        return $route;
    }

}