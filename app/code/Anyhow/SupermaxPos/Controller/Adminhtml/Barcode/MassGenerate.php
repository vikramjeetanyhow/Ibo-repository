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

use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Backend\App\Action;
use Magento\Catalog\Controller\Adminhtml\Product;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * Generate Barcode for a batch of products.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MassGenerate extends \Magento\Catalog\Controller\Adminhtml\Product implements HttpPostActionInterface
{
    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Price\Processor
     */
    protected $_productPriceIndexerProcessor;

    /**
     * MassActions filter
     *
     * @var Filter
     */
    protected $filter;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    protected $resourceConnection;

    /**
     * @param Action\Context $context
     * @param Builder $productBuilder
     * @param \Magento\Catalog\Model\Indexer\Product\Price\Processor $productPriceIndexerProcessor
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        Product\Builder $productBuilder,
        \Magento\Catalog\Model\Indexer\Product\Price\Processor $productPriceIndexerProcessor,
        \Anyhow\SupermaxPos\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        Filter $filter,
        CollectionFactory $collectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Filesystem $filesystem
    ) {
        $this->filter = $filter;
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->collectionFactory = $collectionFactory;
        $this->_productPriceIndexerProcessor = $productPriceIndexerProcessor;
        parent::__construct($context, $productBuilder);
        $this->_storeManager = $storeManager;
        $this->_filesystem = $filesystem;
    }

    /**
     * generate mass product(s) barcodes action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $productIds = $collection->getAllIds();
        $requestStoreId = $storeId = $this->getRequest()->getParam('store', null);
        $ahBarcodePrefix = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_barcode_configutaion/ah_supermax_pos_barcode_prefix');
        $ahBarcodeBasedOn = $this->helper->getConfig('ah_supermax_pos_configuration/ah_supermax_pos_barcode_configutaion/ah_supermax_pos_generateb_barcoded_with');
        $filterRequest = $this->getRequest()->getParam('filters', null);
        $ahBarcodeValue = '';

        if (null === $storeId && null !== $filterRequest) {
            $storeId = (isset($filterRequest['store_id'])) ? (int) $filterRequest['store_id'] : 0;
        }
        
        $connection= $this->resource->getConnection();
        $ahProductTable = $this->resource->getTableName('catalog_product_entity');

        try {
            foreach($collection as $item)
            {
                $ahProductId =  $item->getId();
                if($ahBarcodeBasedOn ==='productId' || empty($ahBarcodeBasedOn)) {
                    $ahBarcodeValue = $ahBarcodePrefix.$ahProductId;
                } elseif($ahBarcodeBasedOn ==='sku'){
                    $ahProductSku = $item->getSku();
                    $ahBarcodeValue = $ahProductSku;
                }
                $where = $connection->quoteInto('entity_id = ?', $ahProductId);
                $query = $connection->update($ahProductTable,
                        ['barcode'=> $ahBarcodeValue, 'barcode_type'=> 1 ],$where );
                                        
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
            }
                
            $this->messageManager->addSuccessMessage(
                __('You have successfully generated barcode for %1 record(s).', count($productIds))
            );
            $this->_productPriceIndexerProcessor->reindexList($productIds);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while generating the product(s) barcode.')
            );
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('supermax/product/index', ['store' => $requestStoreId]);
    }
}
