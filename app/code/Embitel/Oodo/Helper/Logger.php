<?php

namespace Embitel\Oodo\Helper;

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
              "oodo/customer_push/cron_log_active"
          );
          if ($this->isLogEnable) {
              $filename = BP . '/var/log/oodo-customer-push.log';
              $writer = new \Zend\Log\Writer\Stream($filename);
              $logger = new \Zend\Log\Logger();
              $logger->addWriter($writer);
              $this->logger = $logger;
          }
      }
      return $this->isLogEnable;
  }

}