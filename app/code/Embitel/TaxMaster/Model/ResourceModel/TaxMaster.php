<?php
/**
 * TaxMaster Grid ResourceModel.
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Model\ResourceModel;

/**
 * TaxMaster Grid mysql resource.
 */
class TaxMaster extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('catalog_product_entity_varchar', 'value_id');
    }
}
