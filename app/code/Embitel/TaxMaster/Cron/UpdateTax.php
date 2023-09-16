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

class UpdateTax
{
    /**
     * @var $logger
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
        $this->addLog("Cronjob tax rate update execution started");
        $isCronEnable = $this->_scopeConfig->getValue("tax_master/cron_settings/cron_status_update");
        if (!$isCronEnable) {
            $this->addLog("Cronjob tax rate update is disabled.");
            return;
        }
        $hsnCodeId = $this->_eavAttribute->getIdByCode('catalog_product', 'hsn_code');
        $collection = $this->taxMasterFactory->create()
            ->addFieldToSelect('value')
            ->addFieldToFilter('attribute_id', $hsnCodeId)->distinct(true)
            ->setOrder('value', 'ASC');

        foreach ($collection->getData() as $hsnData) {
            $taxRate = '';
            $curlData = $this->getTaxApi->getTax($hsnData['value']);
            if (isset($curlData->tax->tax_rate)) {
                $taxRate = $curlData->tax->tax_rate;
            }
            try {
                if (!empty($hsnData['value']) && strpos($hsnData['value'], "\n") == FALSE) {
                    $this->addLog("HSN Code: " . $hsnData['value'] . " Tax Rate: " . $taxRate);
                    $taxMaster = $this->gridFactory->create();

                    $taxMaster->load($hsnData['value'],'hsn_code');

                    if ($taxMaster->getId()) {
                        $taxMaster->setData('id',$taxMaster->getId());
                    }
                    $taxMaster->setData('hsn_code', $hsnData['value']);
                    $taxMaster->setData('tax_class_id',$taxRate);
                    $this->taxUpdateRepository->save($hsnData['value'], $taxRate);
                    $taxMaster->save();
                }
            } catch (LocalizedException $exception) {
                $this->addLog("Catch");
                $this->addLog($exception->getMessage());
            }
        }
        $this->addLog("Cronjob tax rate update is execution end.");
    }

    /**
     * Add the log
     *
     * @param string $logdata
     * @return string
     */
    public function addLog($logdata)
    {
        if ($this->canWriteLog()) {
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/taxrate-update.log');
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
     * Check writing log enable or not
     *
     * @return void
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
