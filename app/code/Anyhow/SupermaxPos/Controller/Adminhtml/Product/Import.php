<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Product;
use Magento\Framework\Controller\ResultFactory;

class Import extends \Magento\Backend\App\Action
{
    protected $csv;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\File\Csv $csv,
        \Anyhow\SupermaxPos\Model\SupermaxProductFactory $supermaxProduct,
        \Magento\Framework\Registry $coreRegistry
    )
    {
        $this->csv = $csv;
        $this->supermaxProduct = $supermaxProduct;
        $this->coreRegistry = $coreRegistry;
        parent::__construct($context);
    }
    public function execute()
    {
        $rowData = $this->supermaxProduct;
        $this->coreRegistry->register('row_data', $rowData);
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->getConfig()->getTitle()->prepend(__('Import Barcode Data'));
        return $resultPage;
    }
}