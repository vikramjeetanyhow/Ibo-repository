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
class Printbarcode extends \Magento\Backend\App\Action
{
	protected $resultPageFactory;
	
	public function __construct(
		\Magento\Backend\App\Action\Context $context,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory
	) {
		parent::__construct($context);
		$this->resultPageFactory = $resultPageFactory;
	}

	public function execute()
	{
		$data = $this->getRequest()->getParams();

		 /** @var Page $page */
		 $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
		 $page->getConfig()->getTitle()->prepend(__('Add Quantity'));
		 /** @var Template $block */
		 $block = $page->getLayout()->getBlock('print.barcode1');
		 $block->setData('product_parameter', $data['entity_id']);
 
		 return $page;
	}
}