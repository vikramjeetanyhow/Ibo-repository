<?php

// namespace Anyhow\SupermaxPos\Cron;

// use Magento\Framework\App\Filesystem\DirectoryList;

// include_once BP . '/app/code/Anyhow/SupermaxPos/lib/Picqer/Barcode/BarcodeGenerator.php';
// include_once BP . '/app/code/Anyhow/SupermaxPos/lib/Picqer/Barcode/BarcodeGeneratorJPG.php';


// class GenerateProductBarcode
// {
//     public function __construct(
//         \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
//         \Magento\Catalog\Model\ProductFactory $productLoader,
//         \Magento\Framework\App\ResourceConnection $resourceConnection,
//         \Magento\Framework\Filesystem $filesystem
//     ) {
//         $this->productCollectionFactory = $productCollectionFactory;
//         $this->productLoader = $productLoader;
//         $this->resource = $resourceConnection;
//         $this->_filesystem = $filesystem;
//     }

//     public function execute() {
//         $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ah_supermax_barcode_generate.log');
// 		$logger = new \Zend\Log\Logger();
// 		$logger->addWriter($writer);

//         $perPageRecord = 500;
//         $connection= $this->resource->getConnection();
//         $ahProductTable = $this->resource->getTableName('catalog_product_entity');
//         $ahBarcodeValue = '';
//         $totalProductCollection = $this->productCollectionFactory->create()
//             ->addAttributeToSelect('*')
//             ->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
//             ->addAttributeToFilter('type_id', 'simple');
//         $allProducts = $totalProductCollection->load()->getItems();
//         $totalRecords = count($allProducts);

//         if($totalRecords) {
//             $allPages = floor($totalRecords / $perPageRecord) + 1;

//             for($page = 1; $page <= $allPages; $page++) {
//                 $collection = $this->productCollectionFactory->create()
//                     ->addAttributeToSelect('*')
//                     ->addAttributeToFilter('status',\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
//                     ->addAttributeToFilter('type_id', 'simple')
//                     ->setPageSize($perPageRecord) 
//                     ->setCurPage($page);
//                 $products = $collection->load()->getItems();

//                 if(!empty($products)) {
//                     foreach ($products as $product) {
//                         $ahProductId =  $product->getId();
//                         $ahBarcodeValue = $product->getSku();
//                         try {
//                             if(!empty($ahBarcodeValue)) {
//                                 $where = $connection->quoteInto('entity_id = ?', $ahProductId);
//                                 $query = $connection->update($ahProductTable,
//                                     ['barcode'=> $ahBarcodeValue, 'barcode_type'=> 1 ], $where);
//                                 $barcodeSize = 30;
//                                 $generator = new \Picqer\Barcode\BarcodeGeneratorJPG();
//                                 $mediaPath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath('supermax/barcode/products');
                
//                                 if (!is_dir($mediaPath)) {
//                                     $this->_filesystem->getDirectoryWrite(DirectoryList::MEDIA)->create('supermax/barcode/products');
//                                 }
                
//                                 $filename = $ahBarcodeValue.".jpg";
                                
//                                 file_put_contents($mediaPath.'/'.$filename, $generator->getBarcode($ahBarcodeValue, $generator::TYPE_CODE_128, 1, $barcodeSize));
//                             }
//                         } catch (\Exception $e) {
//                             $logger->info('Barcode is not generated for product: ' . $product->getSku());
//                         }
                
//                     }
//                     $logger->info('Barcode is generated for total no of product: ' . ($perPageRecord * $page));
//                 }
//             }  
//         } else {
//             $logger->info('No product found');
//         }

//         return $this;
//     }
// }
