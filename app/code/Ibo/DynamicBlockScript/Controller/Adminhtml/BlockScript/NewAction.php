<?php

namespace Ibo\DynamicBlockScript\Controller\Adminhtml\BlockScript;

use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Backend\App\Action\Context;
use Magento\Backend\App\Action;

class NewAction extends Action
{

    protected $resultForwardFactory;

    /**
     * Create new action
     *
     * @return \Magento\Backend\Model\View\Result\Forward
     */
    public function __construct(
        ForwardFactory $resultForwardFactory,
        Context $context
    ) {
        $this->resultForwardFactory = $resultForwardFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultForward = $this->resultForwardFactory->create();
        //New action forwarded to the edit controller
        $resultForward->forward('edit');
        return $resultForward;
    }
}
