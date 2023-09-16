<?php

namespace Ibo\Order\Helper;

use \Psr\Log\LoggerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Filesystem\Io\File;

class Logger {

  protected $logger;

  /**
   *
   * @var ScopeConfigInterface
   */
  protected $scopeConfig; 

  /**
   * @var TimezoneInterface
   */
  private $date;

  /**
   * @var File
   */
  private $file;

  /**
   * @param LoggerInterface $logger
   * @param ScopeConfigInterface $scopeConfig
   * @param TimezoneInterface $date
   * @param File $file
   */
  public function __construct(
      LoggerInterface $logger,
      ScopeConfigInterface $scopeConfig,
      TimezoneInterface $date,
      File $file
  ) {
    $this->logger = $logger;
    $this->scopeConfig = $scopeConfig;     
    $this->date = $date; 
    $this->file = $file;
  }

  public function addLog($logdata)
  {
      if ($this->canWriteLog()) {
          $this->logger->info($logdata);
      }
  }

  protected function canWriteLog()
  {
      if (!isset($this->isLogEnable)) {
          $this->isLogEnable = $this->scopeConfig->getValue(
              "sales_order_partner/ir_conversion/cron_log_status"
          );
          if ($this->isLogEnable) {
              $filename = BP . '/var/log/ir-conversion.log';
              $writer = new \Zend\Log\Writer\Stream($filename);
              $logger = new \Zend\Log\Logger();
              $logger->addWriter($writer);
              $this->logger = $logger;
          }
      }
      return $this->isLogEnable;
  }

}