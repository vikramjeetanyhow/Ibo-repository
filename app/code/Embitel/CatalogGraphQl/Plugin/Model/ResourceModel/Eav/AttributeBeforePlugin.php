<?php
namespace Embitel\CatalogGraphQl\Plugin\Model\ResourceModel\Eav;


class AttributeBeforePlugin
{
    /**
     * @inheritdoc
     */
    public function beforeBeforeSave(
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $subject
    ) {
        if (is_array($subject['attribute_category_ids'])) {
            $attributeCategoryIds = json_encode($subject['attribute_category_ids']);
            $subject->setData('attribute_category_ids',$attributeCategoryIds);
        } else {
            $subject->setData('attribute_category_ids',NULL);
        }
        return null;
    }
}
