<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Model\Supermax;

class MailOrder implements \Anyhow\SupermaxPos\Api\Supermax\MailOrderInterface
{

    public function __construct(
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    ){
        $this->helper = $helper;
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
    }

   /**
     * POST API
     * @api
     * 
     * @return string
     */
    public function mailOrder()
    {
        $result = array();
        $error = false;

        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $params = $this->helper->getParams();
                if(isset($params['data']) && !empty($params['data'])){
                    $orderId = $params['data']['orderId'];
                    $order = $this->orderRepository->get($orderId);
                    $this->orderSender->send($order, true);
                } else {
                    $error = true;
                }
            } else {
                $error = true;
            }
        } catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => $error, 'result' => $result);
        return json_encode($data);
    }
}