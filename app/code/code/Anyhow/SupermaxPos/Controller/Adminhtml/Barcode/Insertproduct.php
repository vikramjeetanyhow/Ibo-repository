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
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class Insertproduct extends \Magento\Backend\App\Action implements \Magento\Framework\App\Action\HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Backend\Model\View\Result\Redirect $resultRedirect,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
		$this->resource = $resourceConnection;
		$this->resultRedirect = $resultRedirect;
        $this->supermaxSession = $supermaxSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_filesystem = $filesystem;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $outletId = $data['outlet_id'];
        $barcode = $data['barcode-input'];
        $error = '';
        $message = '';
        
        $connection= $this->resource->getConnection();
        $productTable = $this->resource->getTableName('catalog_product_entity');
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $outletProductTable = $this->resource->getTableName('ah_supermax_pos_product_to_outlet');
        try {
            $outletData = $connection->query("SELECT product_assignment_basis FROM $outletTable WHERE pos_outlet_id = $outletId")->fetchAll();
            if(!empty($outletData)){
                foreach($outletData as $outlet){
                    $productAssignmentBasis = $outlet['product_assignment_basis'];
                    if($productAssignmentBasis == 'product'){
                        $productData = $connection->query("SELECT entity_id FROM $productTable WHERE barcode = '$barcode'")->fetchAll();
                        if(!empty($productData)){
                            foreach($productData as $product){
                                $productId = $product['entity_id'];

                                $outletProductData = $connection->query("SELECT * FROM $outletProductTable WHERE product_id = $productId AND parent_outlet_id = $outletId")->fetchAll();
                                if(empty($outletProductData)){
                                    $connection->insert($outletProductTable,
                                    ['parent_outlet_id' => $outletId, 'product_id'=> $productId]);
                                }
                            }
                            $message = 'You have successfully assigned product';
                        }
                    } else {
                        $error = 'You did not save "Product Assignment basis" as "Product based".';
                    }
                }
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $error = $e;
        } catch (\Exception $e) {
            $error = 'Something went wrong while assigning the product(s).';
        }
        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData(['data' => $message, 'error'=> $error]);
    }
}
