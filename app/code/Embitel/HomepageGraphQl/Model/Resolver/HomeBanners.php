<?php

namespace Embitel\HomepageGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Embitel\HomepageGraphQl\Model\Resolver\DataProvider\HomeBanners as HomeBannersDataProvider;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory;

/**
 * Banner field resolver, used for GraphQL request processing.
 */
class HomeBanners implements ResolverInterface
{
    /**
     *
     * @param PageDataProvider $pageDataProvider
     */
    public function __construct(
        HomeBannersDataProvider $homeBannersDataProvider,
        CollectionFactory $collectionFactory
    ) {
        $this->homeBannersDataProvider = $homeBannersDataProvider;
        $this->groupCollection = $collectionFactory;
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $displayZone = !empty($args['display_zone']) ? $args['display_zone'] : null;
        $argCustomerGroup = !empty($args['customer_group_id']) ? $args['customer_group_id'] : null;
        $groupType = $this->getCustomerGroupId($argCustomerGroup);
        if(empty($groupType)) {
            $groupType = $context->getExtensionAttributes()->getCustomerGroupId();
        }
        $data = $this->homeBannersDataProvider->getBannersData($groupType, $displayZone);
        return $data;
    }

    /**
     * @desc get customer group id by name
     * @param $groupName
     * @return mixed|void
     */
    public function getCustomerGroupId($groupName){
        $collection = $this->groupCollection->create()
            ->addFieldToSelect('customer_group_id')
            ->addFieldToFilter('customer_group_code', $groupName);
        $collection->getSelect();

        if($collection->getSize() > 0) {
            return $collection->getFirstItem()->getData()['customer_group_id'];
        }
    }
}
