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
use Magento\Framework\App\Filesystem\DirectoryList;

class Generate extends \Magento\Backend\App\Action
{
    protected $productLoader;
    protected $helper;
    protected $resultRedirectFactory;

	public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ProductFactory $productLoader,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Controller\Result\RedirectFactory $resultRedirectFactory
	)
	{
        parent::__construct($context);
        $this->productLoader = $productLoader;
        $this->_storeManager = $storeManager;
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->_filesystem = $filesystem;
	}

	public function execute()
	{
        $ahProductId = (int) $this->getRequest()->getParam('id');
        $requestStoreId = $storeId = $this->getRequest()->getParam('store', null);
        $product = $this->productLoader->create()->load($ahProductId);
        $ahProductSku = $product->getSku();
        $ahProductname = $product->getName();
        $connection= $this->resource->getConnection();
        $ahProductTable = $this->resource->getTableName('catalog_product_entity');
        $ahBarcodePrefix = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_barcode_configutaion/ah_supermax_pos_barcode_prefix');
        $ahBarcodeBasedOn = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_barcode_configutaion/ah_supermax_pos_generateb_barcoded_with');
        $ahBarcodeValue = '';
        try {
            if($ahBarcodeBasedOn ==='productId' || empty($ahBarcodeBasedOn)) {
                $ahBarcodeValue = $ahBarcodePrefix.$ahProductId;
            }
            elseif($ahBarcodeBasedOn ==='sku') {
                $ahBarcodeValue = $ahProductSku;
            }
            $where = $connection->quoteInto('entity_id = ?', $ahProductId);
            $query = $connection->update($ahProductTable,
                            ['barcode'=> $ahBarcodeValue, 'barcode_type'=> 1 ], $where );
            
            // To make entry in database for connection update for product.
            $this->helper->connectionUpdateEvent($ahProductId, 'product');

            // if(!empty($ahBarcodeValue)){
            //     $barcodeSize = 30;
            //     $generator = new \Picqer\Barcode\BarcodeGeneratorJPG();
            //     $mediaPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('supermax/barcode/products');

            //     if (!is_dir($mediaPath)) {
            //         $this->_filesystem->getDirectoryWrite(DirectoryList::MEDIA)->create('supermax/barcode/products');
            //     }

            //     $filename = $ahBarcodeValue.".jpg";
                
            //     // save barcode image
            //     file_put_contents($mediaPath.'/'.$filename, $generator->getBarcode($ahBarcodeValue, $generator::TYPE_CODE_128, 1, $barcodeSize));
            // }
            
            
            
            $this->messageManager->addSuccessMessage(
                                __('You have successfully generated barcode for '.$ahProductname.'(ProductId:'.$ahProductId.')')
                            );
        }catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                                $e,
                                __('Something went wrong while generating the product barcode.')
                            );
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('supermax/product/index');
        return $resultRedirect;
    }
}