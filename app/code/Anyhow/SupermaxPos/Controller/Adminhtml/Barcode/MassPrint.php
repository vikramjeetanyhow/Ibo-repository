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
class MassPrint extends \Magento\Catalog\Controller\Adminhtml\Product implements HttpPostActionInterface
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

        /** @var Page $page */
		$page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
		$page->getConfig()->getTitle()->prepend(__('Add Quantity'));
		/** @var Template $block */
		$block = $page->getLayout()->getBlock('mass.print');
		$block->setData('product_parameter', $productIds);

    	return $page;
    }
}
