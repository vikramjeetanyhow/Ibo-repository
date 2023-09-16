<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Outlet;

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
class AssignProducts extends \Magento\Catalog\Controller\Adminhtml\Product implements HttpPostActionInterface
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
        \Magento\Framework\Filesystem $filesystem,
        \Anyhow\SupermaxPos\Model\Session $supermaxSession
    ) {
        $this->filter = $filter;
        $this->helper = $helper;
        $this->resource = $resourceConnection;
        $this->collectionFactory = $collectionFactory;
        $this->_productPriceIndexerProcessor = $productPriceIndexerProcessor;
        parent::__construct($context, $productBuilder);
        $this->_storeManager = $storeManager;
        $this->_filesystem = $filesystem;
        $this->supermaxSession = $supermaxSession;
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
        $outletId = $this->supermaxSession->getPosOutletId();
        
        $connection= $this->resource->getConnection();
        $outletProductTable = $this->resource->getTableName('ah_supermax_pos_product_to_outlet');
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');

        try {
            $outletData = $connection->query("SELECT product_assignment_basis FROM $outletTable WHERE pos_outlet_id = $outletId")->fetchAll();
            if(!empty($outletData)){
                foreach($outletData as $outlet){
                    $productAssignmentBasis = $outlet['product_assignment_basis'];
                    if($productAssignmentBasis == 'product'){
                        if(!empty($productIds)){
                            foreach($productIds as $key=>$value){
                                $outletProductData = $connection->query("SELECT * FROM $outletProductTable WHERE product_id = $value AND parent_outlet_id = $outletId")->fetchAll();
                                if(empty($outletProductData)){
                                    $connection->insert($outletProductTable,
                                    ['parent_outlet_id' => $outletId, 'product_id'=> $value]);
                                }
                            }
                        }
                        $this->messageManager->addSuccessMessage(
                            __('You have successfully assigned %1 record(s).', count($productIds))
                        );
                    } else {
                        $this->messageManager->addErrorMessage( __('You did not save "Product Assignment basis" as "Product based".'));
                    }
                }
            } 
            $this->_productPriceIndexerProcessor->reindexList($productIds);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while assigning the product(s).')
            );
        }
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('supermax/outlet/edit', ['id' => $outletId]);
    }
}
