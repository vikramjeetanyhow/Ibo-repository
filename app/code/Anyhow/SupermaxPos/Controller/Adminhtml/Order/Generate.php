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

class Generate extends \Magento\Backend\App\Action
{
	protected $resultPageFactory = false;
    protected $helper;
	protected $resource;
	protected $productRepository;

	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Anyhow\SupermaxPos\Model\Supermax\PaymentIntentBk $paymentIntentBkFile
	)
	{
		parent::__construct($context);
		$this->resultPageFactory = $resultPageFactory;
        $this->helper = $helper;
		$this->resource = $resourceConnection;
		$this->productRepository = $productRepository;
        $this->paymentIntentBkFile = $paymentIntentBkFile;
	}

	public function execute()
	{
		$result = array();
        $error = false;
		try {
            $orderId =$this->getRequest()->getParam('order_id');
            if($orderId) {
                $accessToken = $this->getOrderCashierAccessToken($orderId);
                $resultData = $this->paymentIntentBkFile->paymentIntentNew($orderId, $accessToken);
                $result = json_decode($resultData);
                if(isset($result->payment_intent_id) && !empty($result->payment_intent_id)) {
                    $this->paymentIntentBkFile->updatePaymentIntent($orderId, $result->payment_intent_id);
                    $this->messageManager->addSuccessMessage(
                        __('You have successfully generated payment intent id for Order id '.$orderId)
                    );
                } else {
                    $this->messageManager->addErrorMessage(__('Payment intent can not be generated for Order id '.$orderId));
                }
            } else {
                $this->messageManager->addErrorMessage(__('Order ID is blank'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while generating Payment Intent id')
            );
        }
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('supermax/order/index');
        return $resultRedirect;
	}

    private function getOrderCashierAccessToken($orderId) {
        $accessToken = "";
        $connection = $this->resource->getConnection();
        $posOrderTable = $this->resource->getTableName('ah_supermax_pos_orders');
        $posApiTable = $this->resource->getTableName('ah_supermax_pos_api');
        $orderData = $connection->query("SELECT * FROM $posApiTable WHERE expire > NOW()")->fetch();
        // $orderData = $connection->query("SELECT pa.token FROM $posOrderTable AS po LEFT JOIN $posApiTable AS pa ON(po.pos_user_id = pa.pos_user_id) WHERE po.order_id='" . (int)$orderId . "'")->fetch();
        if(!empty($orderData)) {
            $accessToken = $orderData['token'];
        }
        return $accessToken;
    }
}