<?php
namespace Embitel\CatalogGraphQl\Plugin\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class ProductsBeforePlugin
{
    /**
    * @var \Magento\Framework\Registry
    */
    protected $_registry;

    /**
     * @param \Magento\Framework\Registry $registry
     */
     public function __construct(
        \Magento\Framework\Registry $registry
     ) {
        $this->_registry = $registry;
     }

    /**
     * @inheritdoc
     */
    public function beforeResolve(
        \Magento\CatalogGraphQl\Model\Resolver\Products $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (isset($args['excludeAttr'])) {
            $this->_registry->register('excludeAttr', $args['excludeAttr']);
            unset($args['excludeAttr']);
        }
    }
}
