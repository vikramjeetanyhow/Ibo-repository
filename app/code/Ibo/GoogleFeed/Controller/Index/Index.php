<?php
 
 namespace Ibo\GoogleFeed\Controller\Index;
 
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Action;
 
class Index extends Action
{
    protected $resultPageFactory;
 
    public function __construct(

        \Ibo\GoogleFeed\Cron\PrimaryFeedCron $googlefeed,
        \Ibo\GoogleFeed\Cron\GmcOfferIdsCron $gmclefeed,
        Context $context, 
        PageFactory $resultPageFactory)
    {   
        $this->_googlefeed = $googlefeed;
        $this->_gmclefeed = $gmclefeed;
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }
 
    public function execute()
    {
        echo("Meetanshi Extension testing");
        $this->_gmclefeed->execute();
        
    }
}