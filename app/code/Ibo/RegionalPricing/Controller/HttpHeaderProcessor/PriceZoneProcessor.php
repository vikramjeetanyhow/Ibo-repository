<?php
namespace Ibo\RegionalPricing\Controller\HttpHeaderProcessor;

use Magento\GraphQl\Controller\HttpHeaderProcessorInterface;
use Ibo\RegionalPricing\Model\PriceZoneManagerInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Api\StoreCookieManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Process the "Price-Zone" header entry
 */
class PriceZoneProcessor implements HttpHeaderProcessorInterface
{
    /**
     * @var PriceZoneManagerInterface
     */
    private $priceZoneManager;

    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param PriceZoneManagerInterface $priceZoneManager
     * @param HttpContext $httpContext
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        PriceZoneManagerInterface $priceZoneManager,
        HttpContext $httpContext,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->priceZoneManager = $priceZoneManager;
        $this->httpContext = $httpContext;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * Handle the value of the price zone
     *
     * @see \Magento\Store\App\Action\Plugin\Context::beforeDispatch
     *
     * @param string $headerValue
     * @return void
     */
    public function processHeaderValue(string $headerValue) : void
    {
        $defaultZone = $this->scopeConfig
                            ->getValue("regional_pricing/setting/default_zone",
                                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                                    $this->storeManager->getStore()->getStoreId()
                                    );

        if (!empty($headerValue)) {
            $priceZone = strtolower(trim($headerValue));
            $this->priceZoneManager->setCurrentPriceZone($priceZone);
            $this->updateContext($priceZone,$defaultZone);
        } else {
            $priceZone = strtolower(trim($defaultZone));
            $this->priceZoneManager->setCurrentPriceZone($priceZone);
            $this->updateContext($priceZone,$defaultZone);
        }
    }

    /**
     * Update context accordingly to the prize zone found.
     *
     * @param string $priceZone
     * @param string $defaultZone
     * @return void
     */
    private function updateContext(string $priceZone, string $defaultZone) : void
    {
        $this->httpContext->setValue(
            PriceZoneManagerInterface::CONTEXT_PRICE_ZONE,
            $priceZone,
            $defaultZone
        );
    }
}
