<?php

namespace Embitel\ProductImport\Cron;

use Embitel\ProductImport\Model\ResourceModel\EboImport\CollectionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as DriverFile;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Config\ScopeConfigInterface;

class EboImport
{
    /**
     *
     * @var CollectionFactory
     */
    protected $eboCol;
    
    /**
     *
     * @var TimezoneInterface
     */
    protected $timezone;

    protected $scopeConfig;

    /**
     *
     * @param CollectionFactory $eboCollection
     * @param TimezoneInterface $timezone
     * @param Filesystem $filesystem
     * @param DriverFile $file
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $eboCollection,
        TimezoneInterface $timezone,
        Filesystem $filesystem,
        DriverFile $file
    ) {
       
        $this->eboCol = $eboCollection;
        $this->scopeConfig = $scopeConfig;
        $this->timezone = $timezone;
        $this->filesystem = $filesystem;
        $this->file = $file;
    }
    
    /**
     * import source price
     * @return void
     */
    public function execute()
    {
        //delete recored older than 7 days. done
        $this->deleteOldRecords();
    }
    
    public function deleteOldRecords()
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $path = $directory->getAbsolutePath() . 'export/';
        $isCronEnable = $this->scopeConfig->getValue("ebo/ebo_config/cron_status");
        $saveFilesDays = $this->scopeConfig->getValue("ebo/ebo_config/ebo_save_file_days")?$this->scopeConfig->getValue("ebo/ebo_config/ebo_save_file_days"):7;

        if (!$isCronEnable) {
            $this->addLog("Cronjob product import file cleanup is disabled.");
            return;
        }

        $date = date('Y-m-d', strtotime(
            "-".$saveFilesDays." days",
            strtotime($this->timezone->date()->format('Y-m-d H:i:s'))
        ));
        try {
            $collection = $this->eboCol->create()
                ->addFieldToSelect(['success_filename', 'odoo_filename', 'failure_filename'])
                ->addFieldToFilter('created_at', ['lt' => $date]);
            
            if ($collection->getSize() > 0) {
                foreach ($collection->getData() as $fileName) {
                    unset($fileName['history_id']);
                    foreach ($fileName as $key => $value) {
                        if ($directory->isFile($path.$value)) {
                            $this->file->deleteFile($path.$value);
                            $this->addLog("File deleted : ".$path.$value);
                        } elseif (!is_null($value)) {
                            $this->addLog("File not found : ".$path.$value);
                        }
                    }
                }
                $collection->walk('delete');
            }
        } catch (LocalizedException $e) {
            $this->addLog($e->getMessage());
        } catch (\Exception $e) {
            $this->addLog($e->getMessage());
        }
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
                "ebo/ebo_config/productimport_log_active"
            );
            if ($this->isLogEnable) {
                $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/productimport_cleanup.log');
                $logger = new \Zend\Log\Logger();
                $logger->addWriter($writer);
                $this->logger = $logger;
            }
        }
        return $this->isLogEnable;
    }
}
