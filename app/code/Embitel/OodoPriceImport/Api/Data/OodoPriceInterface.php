<?php
namespace Embitel\OodoPriceImport\Api\Data;

interface OodoPriceInterface extends \Magento\Framework\Api\ExtensibleDataInterface
{
    /**#@+
     * Constants
     */
    const OFFER_ID = 'offer_id';
    const BASE_PRICE_DATA = 'base_price_data';
    const TIER_PRICES_DATA = 'tier_prices_data';
    /**#@-*/

    /**
     * Set Offer ID.
     *
     * @param string $offerId
     * @return $this
     */
    public function setOfferId($offerId);

    /**
     * Get Offer ID.
     *
     * @return string
     */
    public function getOfferId();

    /**
     * Set Base Price Data.
     *
     * @param string|null $basePriceData
     * @return $this
     */
    public function setBasePriceData($basePriceData);

    /**
     * Get Base Price Data of Product.
     *
     * @return string
     */
    public function getBasePriceData();

    /**
     * Set All Price Tiers of Product.
     *
     * @param string $tierPricesData
     * @return $this
     */
    public function setTierPricesData($tierPricesData);

    /**
     * Get All Price Tiers of Product.
     *
     * @return string
     */
    public function getTierPricesData();

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Embitel\OodoPriceImport\Api\Data\OodoPriceExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param \Embitel\OodoPriceImport\Api\Data\OodoPriceExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Embitel\OodoPriceImport\Api\Data\OodoPriceExtensionInterface $extensionAttributes
    );
}
