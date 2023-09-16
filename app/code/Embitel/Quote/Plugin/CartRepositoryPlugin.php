<?php
/**
 * @category   Embitel
 * @package    Embitel_Quote
 * @author     vivekanandan.s@embitel.com 
 */
namespace Embitel\Quote\Plugin;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtension;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartSearchResultsInterface;
use Magento\Sales\Model\Increment;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class CartRepositoryPlugin
 */
class CartRepositoryPlugin
{
    /**
     * @var Increment
     */
    private $increment;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Embitel\Quote\Helper\Data
     */
    private $helper;

    /**
     * CartRepositoryPlugin constructor.
     *
     * @param Increment $increment
     * @param StoreManagerInterface $storeManager
     * @param \Embitel\Quote\Helper\Data $helper
     */
    public function __construct(
        Increment $increment,
        StoreManagerInterface $storeManager,
        \Embitel\Quote\Helper\Data $helper
    ) {
        $this->increment = $increment;
        $this->storeManager = $storeManager;
        $this->helper = $helper;
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param CartInterface $result
     * @return array
     */
    public function beforeSave(
        CartRepositoryInterface $subject,
        CartInterface $quote
    ) {
        if(empty($quote->getReservedOrderId()) 
            && $this->helper->getPromiseStatus()){
            $storeId = $this->storeManager->getStore()->getStoreId();
            $reservedOrderId = $this->increment->getNextValue($storeId);
            $quote->setReservedOrderId($reservedOrderId);
        }
        return [$quote];
    }
}