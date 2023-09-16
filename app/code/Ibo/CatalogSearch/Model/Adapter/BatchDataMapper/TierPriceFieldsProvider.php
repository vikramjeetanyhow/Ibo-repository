<?php
namespace Ibo\CatalogSearch\Model\Adapter\BatchDataMapper;

use Embitel\AdvancedSearch\Model\ResourceModel\Index;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;

/**
 * Provide data mapping for price fields
 */
class TierPriceFieldsProvider implements AdditionalFieldsProviderInterface
{ 
    /**
     * @var Index
     */
    private $resourceIndex;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param StoreManagerInterface $storeManager
     * @param Index $resourceIndexCustom
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Index $resourceIndexCustom
    ) {
        $this->resourceIndexCustom = $resourceIndexCustom;
        $this->storeManager = $storeManager;
    }
    
    /**
     * @inheritdoc
     */
    public function getFields(array $productIds, $storeId)
    { 
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();
        $priceData = $this->resourceIndexCustom->getPriceIndexData($productIds, $storeId,true);
        $fields = [];
        foreach ($productIds as $productId) { 
            if (isset($priceData[$productId]['tier_price'])) {
                $fields[$productId]['ibo_tier_price'] = json_encode($priceData[$productId]['tier_price']);
            } else {
                $fields[$productId]['ibo_tier_price'] = [];
            }
        }
        return $fields;
    }
}
