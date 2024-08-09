<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Barcode;

class Add  extends \Magento\Backend\App\Action
{
    protected $jsonFactory;

    public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Anyhow\SupermaxPos\Helper\Data $helper
    ) {
		parent::__construct($context);
		$this->resource = $resourceConnection;
        $this->jsonFactory = $jsonFactory;
        $this->helper = $helper;
    }

    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->jsonFactory->create();
        $error = false;
        $messages = [];
		$connection= $this->resource->getConnection();
        $ahProductTable = $this->resource->getTableName('catalog_product_entity'); //Table name
        if ($this->getRequest()->getParam('isAjax')) {
			$postItems = $this->getRequest()->getParam('items', []);
            if (!count($postItems)) {
                $messages[] = __('Please correct the data sent.');
                $error = true;
            } else {
				foreach (array_keys($postItems) as $entityId) 
				{
					$where = $connection->quoteInto('entity_id = ?', $entityId);
                    try {
						$query = $connection->update($ahProductTable,
                            ['barcode'=> $postItems[$entityId]['barcode'], 'barcode_type'=> 2 ], $where);
                        
                        // To make entry in databse for connection update for product.
                        $this->helper->connectionUpdateEvent($entityId, 'product');
                    } catch (\Exception $e) {
                        $messages[] = "[Error:]  {$e->getMessage()}";
                        $error = true;
                    }
                }
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $error
        ]);
    }
}