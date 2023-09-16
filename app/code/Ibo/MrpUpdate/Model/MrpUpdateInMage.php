<?php
namespace Ibo\MrpUpdate\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use \Ibo\MrpUpdate\Helper\Logger as MRPLogger;
use Ibo\CoreMedia\Helper\Data as ProductPushHelper;
use Embitel\Catalog\Helper\Data as CatalogHelper;

class MrpUpdateInMage
{
    const SENDER_EMAIL = 'trans_email/ident_general/email';
    const SENDER_NAME = 'trans_email/ident_general/name';
    /**
     * @var ProductRepository
     */
    private $productRepository;
   
    /**
     * @var ProductAction
     */
    private $productAction;
    /**
     * @var StoreManager
     */
    private $storeManager;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;
   
    /**
     * @var EventManager
     */
    private $_eventManager;
    
    /**
     * @var DateTimeFactory
     */
    protected $dateTime;

    /**
     * @var MRPLogger
     */
    private $_mrpLogger;

    /**
     * @var Construct
     * @param string $productRepository
     * @param string $action
     * @param string $storeManager
     * @param string $scopeConfigInterface
     * @param string $transportBuilder
     * @param DateTimeFactory $dateTime
     * @param string $_mrpLogger
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductAction $action,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfigInterface,
        TransportBuilder $transportBuilder,
        EventManager $eventManager,
        DateTimeFactory $dateTime,
        MRPLogger $_mrpLogger,
        ProductPushHelper $productPushHelper,
        CatalogHelper $catalogHelper
    ) {
        $this->productRepository = $productRepository;
        $this->productAction = $action;
        $this->storeManager = $storeManager;
        $this->scopeConfigInterface  = $scopeConfigInterface;
        $this->_transportBuilder = $transportBuilder;
        $this->_eventManager = $eventManager;
        $this->dateTime = $dateTime;
        $this->_mrpLogger = $_mrpLogger;
        $this->productPushHelper = $productPushHelper;
        $this->catalogHelper = $catalogHelper;
    }
    /**
     * @inheritdoc
     */
    public function update($params)
    {
        $response = ['success' => false];
        try {
            $sku = $params['sku'];
            $mrpInput =  $params['mrp'];
            asort($mrpInput);

            $mrpMinimum = array_values($mrpInput)[0]['value'];

            $productInfo = $this->productRepository->get($sku);

            $productId[] = $productInfo->getId();
            $result ='';
            $isPriceGreaterThanMrp = 0;
            if ($mrpMinimum > 0 && $productInfo->getPrice() > $mrpMinimum) {
                $result = "This product's Base price is greater than MRP";
                $isPriceGreaterThanMrp = 1;
            }
            $allTiers = $productInfo->getData('tier_price');

            foreach ($allTiers as $allTier) {
                if ($mrpMinimum > 0 && $allTier['price'] > $mrpMinimum) {
                    $result = __("This product's tier price is greater than MRP");
                    if ($isPriceGreaterThanMrp == 1) {
                        $result = __("This product's base and tier price is greater than MRP");
                    }
                    $isPriceGreaterThanMrp = 1;
                }
            }
            $storeId = $this->storeManager->getStore()->getId();
            //Get current GMT time.
            $dateModel = $this->dateTime->create();
            $current = $dateModel->gmtDate();
            $currentTime = date('Y-m-d H:i:s', strtotime($current));
            $updateAttributes = [
                'old_mrp' => $productInfo->getMrp(),
                'mrp_changeover_message' => $result,
                'mrp' => $mrpMinimum,
                'mrp_updated_at' => $currentTime,
                'two_step_publish_cron' => 0,
                'two_step_status_cron' => 0
            ];
            
            $this->productAction->updateAttributes($productId, $updateAttributes, 0);

            if ($isPriceGreaterThanMrp == 1) {
                // Send Email when MRP is less than base and tier price
                $storeScope = ScopeInterface::SCOPE_STORE;
                $isModuleEnable = $this->scopeConfigInterface->getValue(
                    'mrpprice_update/settings/is_enable',
                    $storeScope
                );
                if ($isModuleEnable == 1) {
                    $emailTemplateVariables = [
                        'sku' => $sku
                    ];
                    $receiverInfo = [
                        'email' => $this->scopeConfigInterface->getValue(
                            'mrpprice_update/settings/reciever_email',
                            $storeScope
                        )
                    ];
                    $senderEmail = $this->scopeConfigInterface->getValue(self::SENDER_EMAIL, $storeScope);
                    $senderName = $this->scopeConfigInterface->getValue(self::SENDER_NAME, $storeScope);
                    $senderInfo = [
                        'name' => $senderName,
                        'email' => $senderEmail,
                    ];
                    $this->temp_id = '';
                    $templateId = 'mrpPriceUpdate';
                    $this->generateTemplate($emailTemplateVariables, $receiverInfo, $senderInfo, $templateId);
                    $transport = $this->_transportBuilder->getTransport();
                    $transport->sendMessage();
                    // End Send Email
                }
            } else {
                $productStatusEnable = \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED;
                $this->productAction->updateAttributes($productId, ['status' => $productStatusEnable], 0);
                $result = __("The MRP RS.".$mrpMinimum." has been updated.");
            }
            $response = ['success' => true, 'message' => $result];

            $this->productPushHelper->updateCatalogServicePushData($productInfo->getId());
            $productAttribute['mrp'] = 'mrp';
            $this->catalogHelper->updateSeldate($sku,$productAttribute);

            //   -------------- Created event to updated mrp in OODO --------------
             $eventParams = new \Magento\Framework\DataObject([
                'sku' => $sku,
                'mrp' => $mrpMinimum
             ]);
            $this->_mrpLogger->addLog('MRP value has been updated successfully in magento for SKU: '.$sku);

            $this->_eventManager->dispatch('update_mrp_from_magento_to_oodo', ['mrp_data' => $eventParams]);
        } catch (\Exception $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
            $this->_mrpLogger->addLog('Error: '.$sku.': '.$e->getMessage());
        }

        $returnArray = json_encode($response);

        return $returnArray;
    }

    /**
     * [generateTemplate description]  with template file and tempaltes variables values
     *
     * @param Mixed $emailTemplateVariables
     * @param Mixed $receiverInfo
     * @param Mixed $senderInfo
     * @param Mixed $templateId
     * @return void
     */
    public function generateTemplate($emailTemplateVariables, $receiverInfo, $senderInfo, $templateId)
    {
        $receiverInfoEmail = str_replace(' ', '', $receiverInfo['email']);
        $recieverEmails = array_map('trim', explode(',', $receiverInfoEmail));
        $storeId = (int)$this->storeManager->getStore()->getId();
        $template = $this->_transportBuilder->setTemplateIdentifier($templateId)
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $storeId,
                ]
            )->setTemplateVars($emailTemplateVariables)
            ->setFrom($senderInfo)
            ->addTo($recieverEmails);
        return $this;
    }
}
