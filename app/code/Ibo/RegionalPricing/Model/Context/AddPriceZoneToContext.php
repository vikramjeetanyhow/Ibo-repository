<?php
namespace Ibo\RegionalPricing\Model\Context;

use Magento\GraphQl\Model\Query\ContextParametersInterface;
use Magento\GraphQl\Model\Query\ContextParametersProcessorInterface;
use Ibo\RegionalPricing\Model\PriceZoneManagerInterface;

/**
 * @inheritdoc
 */
class AddPriceZoneToContext implements ContextParametersProcessorInterface
{
    /**
     * @var PriceZoneManagerInterface
     */
    private $priceZoneManager;

    /**
     * @param PriceZoneManagerInterface $priceZoneManager 
     */
    public function __construct(
        PriceZoneManagerInterface $priceZoneManager
    ) {
        $this->priceZoneManager = $priceZoneManager;
    }

    /**
     * @inheritdoc
     */
    public function execute(ContextParametersInterface $contextParameters): ContextParametersInterface
    {
        $priceZone = $this->priceZoneManager->getCurrentPriceZone();
        $contextParameters->addExtensionAttribute('price_zone', $priceZone);
        return $contextParameters;
    }
}
