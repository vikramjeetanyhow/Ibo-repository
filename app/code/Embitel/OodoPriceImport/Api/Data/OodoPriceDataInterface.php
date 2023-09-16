<?php
namespace Embitel\OodoPriceImport\Api\Data;

use Embitel\OodoPriceImport\Api\Data\OodoPriceInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface OodoPriceDataInterface extends SearchResultsInterface
{
    /**
     * Get categories
     *
     * @return \Embitel\OodoPriceImport\Api\Data\OodoPriceInterface[]
     */
    public function getItems();

    /**
     * Set categories
     *
     * @param \Embitel\OodoPriceImport\Api\Data\OodoPriceInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
