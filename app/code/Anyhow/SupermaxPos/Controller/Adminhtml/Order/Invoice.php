<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Order;
use Magento\Framework\Controller\ResultFactory; 
use Magento\Backend\App\Action;
class Invoice extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;

	public function __construct(
		\Anyhow\SupermaxPos\Helper\Data $helper,
		Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Magento\Framework\Registry $registry
	) {
		$this->helper = $helper;
		$this->resultPageFactory = $resultPageFactory;
		$this->_coreRegistry = $registry;
		parent::__construct($context);	
	}

	public function execute()
	{
		// $orderId = 100053370;
		$orderId =$this->getRequest()->getParam('order_id');
		$baseUrl= $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_invoice_base_url");
		$clientId = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_invoice_api_url", $storeId = null);
		$header = array(
			"Content-Type: application/json",
			"client_id:" . $clientId,
			"trace_id:" . $clientId
		);
		$invoiceApiUrl = $this->helper->getConfig("ah_supermax_pos_configuration/ah_supermax_pos_ibo_api_engine/ah_supermax_pos_invoice_api_url", $storeId = null);
		$apiResponse = $this->helper->curlGetRequest($invoiceApiUrl . "?order=" .$orderId , $header, true, 'Print Invoice');
		//  $apiResponse = $this->helper->curlGetRequest($invoiceApiUrl . "?order=" ."100053370" , $header, true, 'Print Invoice');
		$result = json_decode($apiResponse,true);
		// $invoiceurl = $result['invoices'][0]['invoice_url'];
		$invoiceurl ='';
		foreach($result as $key => $value)
		{
			foreach($value as $invoice)
			{
				$invoiceurl = $invoice['invoice_url'];                                                     
			}
		}
		$viewUrlPath1 = $baseUrl.$invoiceurl;
		// $resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
		$resultRedirect->setPath($viewUrlPath1);
		return $resultRedirect;
		
	
		

	 }
	
}