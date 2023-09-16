<?php

namespace Embitel\CustomerGraphQl\Cron;

use Magento\Framework\App\Area;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\ScopeInterface;
use \Psr\Log\LoggerInterface;
use Magento\ImportExport\Model\Export\Entity\ExportInfoFactory;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\ImportExport\Api\Data\ExportInfoInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\EntityManager\HydratorInterface;
use Magento\ImportExport\Model\ExportFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Ibo\AdvancedPricingImportExport\Model\Mail\TransportBuilder;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Embitel\Oodo\Helper\OodoPush;

class ExportPendingCustomer
{

    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';

    /**
     * @var ExportInfoFactory
     */
    protected $exportInfoFactory;

    /**
     * @var PublisherInterface
     */
    protected $messagePublisher;

    /**
     * @var ExportInfoInterface
     */
    protected $exportInfoInterface;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var HydratorInterface
     */
    protected $hydratorInterface;

    /**
     * @var ExportFactory
     */
    protected $exportFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

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
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var OodoPush
     */
    protected $oodo;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $_customer;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $_customerFactory;

    private $eavConfig;

    /**
     * @param string $context
     * @param string $exportInfoFactory
     * @param string $messagePublisher
     * @param string $exportInfoInterface
     * @param string $hydratorInterface
     * @param string $filesystem
     * @param string $exportFactory
     * @param string $logger
     * @param string $transportBuilder
     * @param string $inlineTranslation
     * @param string $storeManagerInterface
     * @param string $scopeConfigInterface
     * @param string $directoryList
     * @param string $customerFactory
     * @param string $customers
     *
     */
    public function __construct(
        Context $context,
        ExportInfoFactory $exportInfoFactory,
        PublisherInterface $messagePublisher,
        ExportInfoInterface $exportInfoInterface,
        HydratorInterface $hydratorInterface,
        Filesystem $filesystem,
        ExportFactory $exportFactory,
        LoggerInterface $logger,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfigInterface,
        DirectoryList $directoryList,
        \Magento\Framework\App\Response\Http\FileFactory $fileFactory,
        OodoPush $oodo,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\Customer $customers,
        \Magento\Eav\Model\Config $eavConfig
    ) {
        $this->exportInfoFactory = $exportInfoFactory;
        $this->messagePublisher = $messagePublisher;
        $this->exportInfoInterface = $exportInfoInterface;
        $this->hydratorInterface = $hydratorInterface;
        $this->filesystem = $filesystem;
        $this->exportFactory = $exportFactory;
        $this->logger = $logger;
        $this->_transportBuilder = $transportBuilder;
        $this->_inlineTranslation = $inlineTranslation;
        $this->_storeManager = $storeManagerInterface;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->directoryList = $directoryList;
        $this->fileFactory = $fileFactory;
        $this->oodo = $oodo;
        $this->_customerFactory = $customerFactory;
        $this->_customer = $customers;
        $this->eavConfig = $eavConfig;
    }

    /**
     * Customer push to Oodo
     *
     * @return void
     */
    public function execute()
    {
       $storeScope = ScopeInterface::SCOPE_STORE;
       $isModuleEnable =  $this->scopeConfigInterface->getValue(
            'ebo_customer_export/settings/is_enable',
            $storeScope
        );
       if ($isModuleEnable == 1 ) {
           try {
                $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
                $name = date('m_d_Y_H_i_s');
                $filepath = 'export/customer_ebo/customer_' . $name . '.csv';
                $directory->create('export');
               /* Open file */
                $stream = $directory->openFile($filepath, 'w+');
                $stream->lock();
                $columns = $this->getColumnHeader();
                $header = [];
                foreach ($columns as $column) {
                    $header[] = $column;
                }
                /* Write Header */
                $stream->writeCsv($header);

                $unapprovedCustomers = $this->oodo->getUnApprovedCustomers();
                if(!empty($unapprovedCustomers)){
                    foreach($unapprovedCustomers as $customerInfo){
                        $customerData = [];
                        $customerData[] = $customerInfo['mobilenumber'];
                        $customerData[] = $customerInfo['created_at'];
                        $customerData[] = $customerInfo['updated_at'];
                        $customerData[] = $customerInfo['customer_type'];
                        $customerData[] = $customerInfo['customer_name'];
                        $customerData[] = $customerInfo['approval_status'];
                        $stream->writeCsv($customerData);
                    }
                }

               if (!empty($unapprovedCustomers)) {
                   $emailBody = 'Please find unapproved customers.';
                   $this->sendEmail($emailBody, $name);
               } else {
                   $emailBody = 'Something went wrong while export process.';
                   $this->sendEmail($emailBody);
               }
               // exit;
           } catch (LocalizedException | FileSystemException $exception) {
               $emailBody = 'Something went wrong while export process. ' . $exception->getMessage();
               $this->sendEmail($emailBody);
               $this->logger->critical('Something went wrong while export process. ' . $exception->getMessage());
           }
       }else {
           $this->logger->info('Export Pending Customer module is disabled');
       }
    }

    /* Header Columns */
    public function getColumnHeader() {
        $headers = ['Phone Number','Registration Time','Updated Time','Customer Type', 'Customer Name','Status'];
        return $headers;
    }

    private function getCustomerInfo($customerId){
        return $this->_customer->load($customerId);
    }

    private function getCustomerAttributeValue($attributeCode, $value)
    {
        if(!empty($value)){
            $attribute = $this->eavConfig->getAttribute('customer', $attributeCode);
            return $attribute->getSource()->getOptionText($value);
        }else{
            return '';
        }
        
    }

    /**
     * Send Email
     *
     * @param Mixed $emailBody
     * @return void
     */

    public function sendEmail($emailBody, $name='')
    {
        $this->logger->info('Unapproved Ebo Customer report Email Start');
        $this->_inlineTranslation->suspend();
        $emailTemplateVariables = [
            'message' => $emailBody
        ];

        $storeScope = ScopeInterface::SCOPE_STORE;
        $receiverInfo = [
            'email' => $this->scopeConfigInterface->getValue(
                'ebo_customer_export/settings/reciever_email',
                $storeScope
            )
        ];
        $receiverInfoEmail = array_map('trim', explode(',', $receiverInfo['email']));

        $this->logger->info('receiverInfoEmail - ' . $receiverInfo['email']);

        $senderEmail = $this->scopeConfigInterface->getValue(self::SENDER_EMAIL, $storeScope);
        $senderName = $this->scopeConfigInterface->getValue(self::SENDER_NAME, $storeScope);
        $senderInfo = [
            'name' => $senderName,
            'email' => $senderEmail,
        ];

        $this->logger->info('========= Send email ========');

        $varExportPath = $this->directoryList->getPath('var');
        $fileName = $name . '.csv';
        if (!empty($name)) {
            $filePath = $varExportPath.'/export/customer_ebo/customer_' . $name . '.csv';
            $this->logger->info('Path: '. $filePath);
            $this->logger->info('$fileName' . $fileName);
            $storeId = (int)$this->_storeManager->getStore()->getId();

            $transport = $this->_transportBuilder->setTemplateIdentifier('ebo_customer')
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ]
                )->setTemplateVars($emailTemplateVariables)
                ->setFromByScope($senderInfo)
                ->addTo($receiverInfoEmail)
                ->addAttachment(file_get_contents($filePath), $fileName, 'application/csv')
                ->getTransport();
            try {
                $transport->sendMessage();
            } catch (\Exception $e) {
                $this->logger->info($e->getMessage());
            }
            $this->_inlineTranslation->resume();
        } else {
            $this->logger->info('Unapproved Customer Data file not found');
        }
    }
}
