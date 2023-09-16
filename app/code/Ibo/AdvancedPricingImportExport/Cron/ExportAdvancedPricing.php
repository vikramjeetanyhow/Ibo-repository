<?php

namespace Ibo\AdvancedPricingImportExport\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Area;

class ExportAdvancedPricing
{
    public const SENDER_EMAIL = 'trans_email/ident_general/email';
    public const SENDER_NAME = 'trans_email/ident_general/name';

    protected $connection;

    protected $scopeConfigInterface;

    protected $storeRepository;

    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

   /**
     *
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resources
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfigInterface,
        StoreRepository $storeRepository,
        ResourceConnection $resources,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface
    ) {       
        $this->storeRepository = $storeRepository;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->resources = $resources;
        $this->_transportBuilder = $transportBuilder;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_storeManager = $storeManagerInterface;
        $this->connection = $this->resources->getConnection();
    }

    public function execute() {

        echo "\nProduct publish - Start\n";

        $fileName = date('d_m_Y') . "_advanced_pricing_report.csv";

        $files = BP . "/var/scripts/advanced_pricing/*";


        //-----Remove old files------
        $folderName = BP . "/pub/media/advancedPricing/";
        if ($handle = opendir($folderName))  
        {
            while (false !== ($file = readdir($handle)))
            {
                if (is_file($folderName.$file))
                {
                    if (filemtime($folderName.$file) < ( time() - ( 3 * 24 * 60 * 60 ) ) )
                    {
                        unlink($folderName.$file);
                    }
                }
            }
        }
        //-----end Remove old files------

        foreach(glob($files) as $file) {
            unlink($file);
        }

        $catalogSql = "select entity_id from catalog_product_entity where type_id='simple';";

        $attributeSetSql = "select distinct attribute_set_id, attribute_set_name from eav_attribute_set";

        $attributeSetData = array_column($this->connection->fetchAll($attributeSetSql), "attribute_set_name", "attribute_set_id");

        $productList = $this->connection->fetchAll($catalogSql);

        $pagedArray = array_chunk($productList, 5000, true);
        $header = [];
        $count = 0;

        foreach ($pagedArray as $key => $value) {

            $folder = BP . "/var/scripts/advanced_pricing";
            if (!is_dir($folder)) {
                mkdir($folder);
            }
            $path = BP . "/var/scripts/advanced_pricing/" .$key. "_" . $fileName;
            $file = fopen($path, "w");

            $productRowIds = implode("','", array_column($value, "entity_id"));
            $sql = $this->getSql($productRowIds);
            $result = $this->connection->fetchAll($sql);

           if (!$count && count($result)) {
               $header_keys = array_keys($result[0]);
               $header = array_combine($header_keys, $header_keys);
               $this->writeInCsv($header, $file);
               $count++;
           }
           echo "\n" . count($result) . "\n";

            foreach ($result as $data) {
                foreach ($header as $rKey) {
                    if (!isset($data[$rKey])) {
                        $data[$rKey] = ' ';
                    }
                    if ($rKey == 'attribute_set_id') {
                        $data[$rKey] = $attributeSetData[$data[$rKey]];
                    }
                }
                $this->writeInCsv($data, $file, $key);
            }
        }

        $exportedFiles = BP . "/var/scripts/advanced_pricing/*";
        $exportDir = BP . "/pub/media/advancedPricing/" . $fileName;
        try {
            $this->joinFiles(glob($exportedFiles), $exportDir, $fileName);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    public function writeInCsv($record, $file, $key = 0)
    {
        fputcsv($file, $record);
    }


    public function getSql($productRowIds)
    {
        return "select cpe.sku,
           if(cpeis.value = 1, 'ENABLED', 'DISABLED') as `status`,
           if(cpeip.value = 1, 'YES', 'NO')           as `is_published`,
           if(ciss.stock_status, 'In Stock', 'Out Of Stock')         as `is_in_stock`,
           cpem.value                                 as `meta_title`,
           cped.value                                 as `department`,
           cpec.value                                 as `class`,
           cpes.value                                 as 'subclass',
           cg.customer_group_code                     as `tier_price_customer_group`,
           if(cpeil.value = 1, 'YES', 'NO')           as `lot_controlled`,
           cpevlp.value                               as `lot_control_parameters`,
           cpevcc.value                               as `case_config`,
           eaov.value                                 as `pack_of`,
           cg.customer_group_code                     as `tier_price_customer_group`,
           cpev.value                                 as `mrp`,
           cpetp.qty                                  as `tier_price_qty`,
           cpetp.value                                as `tire_price_with_tax`,
           (cpetp.value * 100 / (100 + tcr.rate))     as `tier_price_without_tax`,
           UPPER(cpetp.customer_zone)                        as `price_zone`,
           cpevo.value                                as `allowed_channels` from catalog_product_entity cpe
             left join catalog_product_entity_tier_price cpetp on cpe.entity_id = cpetp.entity_id && cpetp.is_defined_price != 'DERIVED'
             left join customer_group cg on cpetp.customer_group_id = cg.customer_group_id
             left join catalog_product_entity_varchar cpevo on cpevo.entity_id = cpe.entity_id && cpevo.store_id = 0 && cpevo.attribute_id = (select eav_attribute.attribute_id from eav_attribute where attribute_code = 'allowed_channels' && eav_attribute.entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product'))
             left join catalog_product_entity_varchar cpev on cpev.entity_id = cpe.entity_id && cpev.store_id = 0 && cpev.attribute_id = (select eav_attribute.attribute_id from eav_attribute where attribute_code = 'mrp' && eav_attribute.entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product'))
             left join catalog_product_entity_int cpeis on cpeis.entity_id = cpe.entity_id && cpeis.store_id = 0 && cpeis.attribute_id = (select eav_attribute.attribute_id from eav_attribute where attribute_code = 'status' && eav_attribute.entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product'))
             left join cataloginventory_stock_status ciss on ciss.product_id = cpe.entity_id 
             left join catalog_product_entity_int cpeip on cpeip.entity_id = cpe.entity_id && cpeip.store_id = 0 && cpeip.attribute_id = (select eav_attribute.attribute_id from eav_attribute where attribute_code = 'is_published' && eav_attribute.entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product'))
             left join catalog_product_entity_varchar cped on cped.entity_id = cpe.entity_id && cped.store_id = 0 && cped.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code ='department')
             left join catalog_product_entity_varchar cpec on cpec.entity_id = cpe.entity_id && cpec.store_id = 0 && cpec.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code = 'class')
             left join catalog_product_entity_varchar cpes on cpes.entity_id = cpe.entity_id && cpes.store_id = 0 && cpes.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code = 'subclass')
             left join catalog_product_entity_varchar cpem on cpem.entity_id = cpe.entity_id && cpem.store_id = 0 && cpem.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code = 'meta_title')
             left join catalog_product_entity_int cpeil on cpe.entity_id = cpeil.entity_id && cpeil.store_id = 0 && cpeil.attribute_id = (select attribute_id from eav_attribute where entity_type_id =(select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code like 'is_lot_controlled')
             left join catalog_product_entity_varchar cpevlp on cpe.entity_id = cpevlp.entity_id && cpevlp.store_id = 0 && cpevlp.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code like 'lot_control_parameters')
             left join catalog_product_entity_varchar cpevcc on cpe.entity_id = cpevcc.entity_id && cpevcc.store_id = 0 && cpevcc.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code like 'case_config')
             left join catalog_product_entity_int cpeicc on cpeicc.entity_id = cpe.entity_id && cpeicc.store_id = 0 && cpeicc.attribute_id = (select attribute_id from eav_attribute where entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product') && attribute_code like 'pack_of')
             left join eav_attribute_option_value eaov on eaov.option_id = cpeicc.value && eaov.store_id = 0
             left join catalog_product_entity_int cpei on cpei.entity_id = cpe.entity_id && cpei.store_id = 0 && cpei.attribute_id = (select eav_attribute.attribute_id from eav_attribute where attribute_code = 'tax_class_id' && eav_attribute.entity_type_id = (select entity_type_id from eav_entity_type where entity_type_code = 'catalog_product'))
             left join tax_calculation tc on tc.product_tax_class_id = cpei.value
             left join tax_calculation_rate tcr on tcr.tax_calculation_rate_id = tc.tax_calculation_rate_id where cpe.entity_id in ('$productRowIds') && cpetp.value is not null;";
    }

    public function joinFiles(array $files, $exportDir, $fileName) {
        $length = count($files);
        if (!is_array($files)) {
            throw new Exception('`$files` must be an array');
        }
        $wH = fopen($exportDir, "w+");

        foreach($files as $file) {
            if (file_exists($file)) {
                $fh = fopen($file, "r");
                while (!feof($fh)) {
                    fwrite($wH, fgets($fh));
                }
                fclose($fh);
                unset($fh);
                unlink($file);
            }
        }
        fclose($wH);
        unset($wH);
        
        $fileUrl = $this->_storeManager->getStore()->getUrl('media/advancedPricing/').$fileName;
                $this->addLog('FileUrl: '.$fileUrl);
        $emailBody = 'Please find the attached Advanced Pricing Report:- <a href ="'.$fileUrl.'">'.$fileUrl.'</a>';

        $this->sendEmail($emailBody, $fileUrl);
        echo 'Done';
    }

    /**
     * Send Email
     *
     * @param Mixed $emailBody
     * @return void
     */

    public function sendEmail($emailBody, $fileUrl)
    {
        $this->addLog('Advanced pricing report Email Start');
        $this->_inlineTranslation->suspend();
        $emailTemplateVariables = [
            'message' => $emailBody
        ];

        $storeScope = ScopeInterface::SCOPE_STORE;
        $receiverInfo = [
            'email' => $this->scopeConfigInterface->getValue(
                'advanced_pricing/settings/reciever_email',
                $storeScope
            )
        ];
        $receiverInfoEmail = array_map('trim', explode(',', $receiverInfo['email']));

        $senderEmail = $this->scopeConfigInterface->getValue(self::SENDER_EMAIL, $storeScope);
        $senderName = $this->scopeConfigInterface->getValue(self::SENDER_NAME, $storeScope);
        $senderInfo = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        $this->addLog('========= Send email ========');
        $this->addLog('File Url:- '.$fileUrl);
        $this->addLog('File Url time:'. microtime(true));

        $ch = curl_init($fileUrl);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->addLog('responseCode: '.$responseCode);

        if($responseCode == 200) {
            $storeId = (int)$this->_storeManager->getStore()->getId();
            $transport = $this->_transportBuilder->setTemplateIdentifier('advanced_pricing')
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ]
                )->setTemplateVars($emailTemplateVariables)
                ->setFrom($senderInfo)
                ->addTo($receiverInfoEmail)
                ->getTransport();
            try {
                $transport->sendMessage();
                $this->addLog('TRY');
            } catch (\Exception $e) {
                $this->addLog('CATCH');
            }
            $this->addLog('Before resume');
            $this->addLog('Before:'. microtime(true));
            $this->_inlineTranslation->resume();
            $this->addLog('After resume');
            $this->addLog('After:'. microtime(true));

        } else {
            $this->addLog('Advanced pricing file not found');
        }
    }

    public function addLog($logData, $filename = "advancedPricing.log")
    {
        if ($this->canWriteLog($filename)) {
            $this->logger->info($logData);
        }
    }

    protected function canWriteLog($filename)
    {

        $logEnable = 1;
        if ($logEnable) {
            $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Laminas\Log\Logger();
            $logger->addWriter($writer);
            $this->logger = $logger;
        }

        return $logEnable;
    }
}
