<?php

namespace Embitel\OodoPriceImport\Model;

use Embitel\OodoPriceImport\Api\Data\OodoPriceInterface;
use Embitel\OodoPriceImport\Api\Data\OodoPriceExtensionInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

/**
 * @codeCoverageIgnore
 */
class OodoPrice extends AbstractExtensibleModel implements OodoPriceInterface
{
    /**
     * Model construct that should be used for object initialization.
     */
    public function _construct()
    {
        $this->_init('Embitel\OodoPriceImport\Model\ResourceModel\OodoPrice');
    }

    /**
     * @inheritdoc
     */
    public function setOfferId($offerId) {
        return $this->setData(self::OFFER_ID);
    }

    /**
     * @inheritdoc
     */
    public function getOfferId() {
        return $this->getData(self::OFFER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setBasePriceData($basePriceData) {
        return $this->setData(self::BASE_PRICE_DATA);
    }

    /**
     * @inheritdoc
     */
    public function getBasePriceData() {
        return $this->getData(self::BASE_PRICE_DATA);
    }

    /**
     * @inheritdoc
     */
    public function setTierPricesData($tierPricesData) {
        return $this->setData(self::TIER_PRICES_DATA);
    }

    /**
     * @inheritdoc
     */
    public function getTierPricesData() {
        return $this->getData(self::TIER_PRICES_DATA);
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Embitel\OodoPriceImport\Api\Data\OodoPriceExtensionInterface|null
     */
    public function getExtensionAttributes() {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     *
     * @param \Embitel\OodoPriceImport\Api\Data\OodoPriceExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes( OodoPriceExtensionInterface $extensionAttributes ) {
        return $this->_setExtensionAttributes($extensionAttributes);
    }    
}
