<?php
/**
 * @package Embitel_Catalog
 * @author Amar Jyoti
 * 
 */
namespace Embitel\Catalog\Model\Config\Source;

class IsReturnable extends \Magento\Eav\Model\Entity\Attribute\Source\Boolean
{
    /**
     * XML configuration path allow RMA on product level
     */
    //const XML_PATH_PRODUCTS_ALLOWED = 'sales/magento_rma/enabled_on_product';

    
    const ATTRIBUTE_ENABLE_RMA_YES = 1;

    const ATTRIBUTE_ENABLE_RMA_NO = 0;

    const ATTRIBUTE_ENABLE_RMA_USE_CONFIG = 2;

    /**
     * Retrieve all attribute options
     *
     * @return array
     */
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                ['label' => __('Yes'), 'value' => self::ATTRIBUTE_ENABLE_RMA_YES],
                ['label' => __('No'), 'value' => self::ATTRIBUTE_ENABLE_RMA_NO],
            ];
        }
        return $this->_options;
    }
}
