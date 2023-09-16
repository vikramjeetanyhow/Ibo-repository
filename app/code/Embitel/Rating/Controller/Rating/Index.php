<?php

namespace Embitel\Rating\Controller\Rating;

use Magento\Framework\App\Action\Context;
use Magento\Sales\Controller\AbstractController\OrderLoaderInterface;
 
class Index extends \Magento\Framework\App\Action\Action
{
    
    /**
     * @param Action\Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context
        
    ) {
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        parent::__construct($context);
    }
    
    /**
     * Action for cancel
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {        
        $this->_objectManager->create('\Embitel\Rating\Cron\Save')->execute();
        echo 'success';die;
    }   
    
}
