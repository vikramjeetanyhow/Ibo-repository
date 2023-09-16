<?php
/**
 * Copyright © Embitel All rights reserved.
 * See COPYING.txt for license details.
 * The cron is setup to update tax rate as per HSN code.
 */
declare(strict_types=1);

namespace Embitel\TaxMaster\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use \Embitel\TaxMaster\Model\ResourceModel\TaxMaster\CollectionFactory as TaxMasterCollection;
use \Embitel\TaxMaster\Model\GetTaxApi;
use \Embitel\TaxMaster\Model\TaxUpdateRepository;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use \Embitel\TaxMaster\Model\GridFactory;
use Magento\Framework\Exception\LocalizedException;

class SyncTax
{
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var GridFactory
     */
    private $gridFactory;

    /**
     * @var TaxMasterCollection
     */
    protected $taxMasterFactory;
    /**
     * @var Attribute
     */
    protected $_eavAttribute;

    /**
     * @var GetTaxApi
     */
    protected $getTaxApi;

    /**
     * Constructor
     * @param ScopeConfigInterface $scopeConfig
     * @param GridFactory $gridFactory
     * @param TaxMasterCollection $taxMasterFactory
     * @param Attribute $eavAttribute
     * @param GetTaxApi $getTaxApi
     * @param TaxUpdateRepository $taxUpdateRepository
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        GridFactory $gridFactory,
        TaxMasterCollection $taxMasterFactory,
        Attribute $eavAttribute,
        GetTaxApi $getTaxApi,
        TaxUpdateRepository $taxUpdateRepository
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->gridFactory = $gridFactory;
        $this->taxMasterFactory = $taxMasterFactory;
        $this->_eavAttribute = $eavAttribute;
        $this->getTaxApi = $getTaxApi;
        $this->taxUpdateRepository = $taxUpdateRepository;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $this->addLog("Cronjob tax rate sync execution started");
        $isCronEnable = $this->_scopeConfig->getValue("tax_master/cron_settings/cron_status_update");
        if (!$isCronEnable) {
            $this->addLog("Cronjob tax rate sync is disabled.");
            return;
        }
        $hsnCodeId = $this->_eavAttribute->getIdByCode('catalog_product', 'hsn_code');
        $taxMasterCollection = $this->gridFactory->create()->getCollection()
            ->addFieldToSelect(['hsn_code','id']);

        if ($taxMasterCollection->getSize() > 0) {
            foreach ($taxMasterCollection as $hsnData) {
                $taxMaster = $this->gridFactory->create();
                $hsnData = $hsnData->getData();
                $taxRate = '';
                $curlData = $this->getTaxApi->getTax($hsnData['hsn_code']);
                if (isset($curlData->tax->tax_rate)) {
                    $taxRate = $curlData->tax->tax_rate;
                }
                try {
                    if (count($hsnData) && isset($hsnData['id'])) {
                        $taxMaster->setData(['id' => $hsnData['id'], 'tax_class_id' => $taxRate]);
                        $taxMaster->save();
                        $this->taxUpdateRepository->save($hsnData['hsn_code'], $taxRate);
                        $this->addLog("Id: ".$hsnData['id']." HSN Code: ".$hsnData['hsn_code']." TaxRate: ".$taxRate);
                    }
                } catch (LocalizedException $exception) {
                    $this->addLog("Catch");
                    $this->addLog($exception->getMessage());
                }
            }
        }
        $this->addLog("Cronjob tax rate sync is execution end.");
    }

    /**
     * Add Log
     *
     * @param string $logdata
     *
     * @return Log Data
     */
    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/taxrate-synced.log');
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            if ($logdata) {
                $logger->info($logdata);
            } else {
                $logger->info($logdata);
            }
        }
    }

    /**
     * Check Log enable or not
     *
     * @return bool
     */
    protected function canWriteLog()
    {
        if (!isset($this->isLogEnable)) {
            $this->isLogEnable = $this->_scopeConfig->getValue(
                "tax_master/cron_settings/statusupdate_log_active"
            );
        }
        return $this->isLogEnable;
    }
}
