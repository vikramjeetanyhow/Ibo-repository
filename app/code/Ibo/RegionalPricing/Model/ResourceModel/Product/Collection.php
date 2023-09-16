<?php
namespace Ibo\RegionalPricing\Model\ResourceModel\Product;

class Collection extends \Magento\Catalog\Model\ResourceModel\Product\Collection
{
    /**
     * @var bool|string
     */
    private $linkField;

    /**
    * @var \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
    */
    private $backend;

    public function addTierPriceDataByGroupId($customerGroupId)
    {
        if ($this->getFlag('tier_price_added')) {
            return $this;
        }

        $productIds = [];
        foreach ($this->getItems() as $item) {
            $productIds[] = $item->getData($this->getLinkField());
        }
        if (!$productIds) {
            return $this;
        }
        $select = $this->getTierPriceSelect($productIds);
        $select->where(
            '(customer_group_id=? AND all_groups=0) OR all_groups=1',
            $customerGroupId
        );
        $select->columns('customer_zone');
        $this->fillTierPriceData($select);

        $this->setFlag('tier_price_added', true);
        return $this;
    }
    /**
     * Retrieve link field and cache it.
     *
     * @return bool|string
     */
    private function getLinkField()
    {
        if ($this->linkField === null) {
            $this->linkField = $this->getConnection()->getAutoIncrementField($this->getTable('catalog_product_entity'));
        }
        return $this->linkField;
    }

    /**
     * Get tier price select by product ids.
     *
     * @param array $productIds
     * @return \Magento\Framework\DB\Select
     */
    private function getTierPriceSelect(array $productIds)
    {
        /** @var $attribute \Magento\Catalog\Model\ResourceModel\Eav\Attribute */
        $attribute = $this->getAttribute('tier_price');
        /* @var $backend \Magento\Catalog\Model\Product\Attribute\Backend\Tierprice */
        $backend = $attribute->getBackend();
        $websiteId = 0;
        if (!$attribute->isScopeGlobal() && null !== $this->getStoreId()) {
            $websiteId = $this->_storeManager->getStore($this->getStoreId())->getWebsiteId();
        }
        $select = $backend->getResource()->getSelect($websiteId);
        $select->columns(['product_id' => $this->getLinkField()])->where(
            $this->getLinkField() . ' IN(?)',
            $productIds
        )->order(
            'qty'
        );
        return $select;
    }

    /**
     * Fill tier prices data.
     *
     * @param Select $select
     * @return void
     */
    private function fillTierPriceData(\Magento\Framework\DB\Select $select)
    {
        $tierPrices = [];
        foreach ($this->getConnection()->fetchAll($select) as $row) {
            $tierPrices[$row['product_id']][] = $row;
        }
        foreach ($this->getItems() as $item) {
            $productId = $item->getData($this->getLinkField());
            $this->getBackend()->setPriceData($item, isset($tierPrices[$productId]) ? $tierPrices[$productId] : []);
        }
    }

    /**
     * Retrieve backend model and cache it.
     *
     * @return \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
     */
    private function getBackend()
    {
        if ($this->backend === null) {
            $this->backend = $this->getAttribute('tier_price')->getBackend();
        }
        return $this->backend;
    }
}
