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

use Magento\Framework\DataObject;

class AllOrderStatus extends DataObject implements \Anyhow\SupermaxPos\Api\Supermax\AllOrderStatusInterface
{    
    public function __construct( 
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ){
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * GET for Post api
     * @api
     * 
     * @return string
     */
 
    public function getAllOrderStatus()
    {
        $result = array();
        $error = false;
        try {
            $tokenFlag = $this->helper->userAutherization();
            $this->helper->setHeaders();

            if($tokenFlag) {
                $statuses = array();
                $orderStatusCollection = $this->statusCollectionFactory->create();
                $orderStatus = $orderStatusCollection->getData();
                
                if(!empty($orderStatus)) {
                    foreach($orderStatus as $orderSt) {
                        $statuses[] = array(
                            'status_code' => html_entity_decode($orderSt['status']),
                            'status_label' => html_entity_decode($orderSt['label'])
                        );
                    }
                }   

                $result = array('statuses' => $statuses);

            } else {
                $error = true;
            }
        }catch (\Exception $e) {
            $error = true;
        }
        $data = array('error' => (bool)$error, 'result' => $result);
        return json_encode($data);
    }
}