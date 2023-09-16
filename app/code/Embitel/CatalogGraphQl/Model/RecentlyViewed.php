<?php
namespace Embitel\CatalogGraphQl\Model;

use Magento\Reports\Model\ReportStatus;
use Magento\Reports\Model\Event;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Reports\Model\Product\Index\ViewedFactory;
use Magento\Customer\Model\Visitor;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Psr\Log\LoggerInterface;

class RecentlyViewed
{
     /**
     * @var ReportStatus
     */
    private $reportStatus;
    
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ViewedFactory
     */
    protected $_productIndxFactory;

    /**
     * @var Visitor
     */
    protected $_customerVisitor;

    /**
     * @var PriceHelper
     */
    protected $priceHelper;

    /**
     * @var ImageFactory
     */
    protected $productImageFactory;

    /**
     * @var TimezoneInterface
     */
    protected $timezoneInterface;

    private $logger;
    
    /**
     * @param ReportStatus $reportStatus
     * @param StoreManagerInterface $storeManager
     * @param ViewedFactory $productIndxFactory
     * @param Visitor $customerVisitor
     * @param TimezoneInterface $timezoneInterface
     * @param LoggerInterface $logger
     */
    public function __construct(
        ReportStatus $reportStatus,
        StoreManagerInterface $storeManager,
        ViewedFactory $productIndxFactory,
        Visitor $customerVisitor,
        TimezoneInterface $timezoneInterface,
        LoggerInterface $logger
    ) {
        $this->reportStatus = $reportStatus;
        $this->_storeManager = $storeManager;
        $this->_productIndxFactory = $productIndxFactory;
        $this->_customerVisitor = $customerVisitor;
        $this->timezoneInterface = $timezoneInterface;
        $this->logger = $logger;
    }
        
    /**
     * Return Current Date Time
     */
    public function getDatetime()
    {
        return $this->timezoneInterface->date(null, null, false)->format('Y-m-d H:i:s');
    }

    /**
     * Get recently viewed products
     *
     * @param int $customerId
     * @param int $productId
     * @return void
     * @throws LocalizedException
     */
    public function addItem($customerId, $productId)
    {
        try {
            if (!$this->reportStatus->isReportEnabled(Event::EVENT_PRODUCT_VIEW)) {
                throw new LocalizedException(__("Please enable Product View Report"));
            }
            $viewData['product_id'] = $productId;
            $viewData['store_id']   = $this->_storeManager->getStore()->getId();
            if ($customerId) {
                $viewData['customer_id'] = $customerId;
            } else {
                $viewData['visitor_id'] = $this->_customerVisitor->getId();
            }
            $viewData['added_at'] = $this->getDatetime();
            $this->_productIndxFactory->create()->setData($viewData)->save()->calculate();
        } catch (\Exception $e) {
            $this->logger->critical('Recently Viewed Graphql Error:', ['exception' => $e]);
            throw new LocalizedException (__("Something went wrong while adding the product."));
        }
        return true;
    }
}
