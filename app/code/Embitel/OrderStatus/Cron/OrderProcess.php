<?php
namespace Embitel\OrderStatus\Cron;
use Embitel\Quote\Helper\Data;

class OrderProcess
{
/**
     * @var Data
     */
    protected $helper;
    public function __construct(  
        Data $helper 
    ) {  
        $this->helper = $helper;  
    }
	public function execute()
	{
     $this->helper->addLog("---------------------------------------------", "oms-promise.log");
     $this->helper->addLog("OMS Cron Started", "oms-promise.log");
      $this->helper->OrderFillFullmentResponse();
      $this->helper->addLog("OMS Cron End", "oms-promise.log");
      $this->helper->addLog("---------------------------------------------", "oms-promise.log");
	}
    
}