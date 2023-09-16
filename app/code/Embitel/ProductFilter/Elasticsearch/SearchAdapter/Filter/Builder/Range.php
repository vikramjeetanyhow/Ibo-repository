<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductFilter\Elasticsearch\SearchAdapter\Filter\Builder;

use Magento\Framework\Search\Request\Filter\Range as RangeFilterRequest;
use Magento\Framework\Search\Request\FilterInterface as RequestFilterInterface;

class Range extends \Magento\Elasticsearch\SearchAdapter\Filter\Builder\Range
{
    /**
     * @param RequestFilterInterface|RangeFilterRequest $filter
     * @return array
     */
    public function buildFilter(RequestFilterInterface $filter)
    {
//        start custom code for multiple price filter
//        example: price: {from: "10:50", to: "20:60"}
//        It will filter product having price from10to20 or price from50to60
        if ($filter->getField() == 'price') {
            $filterQuery = $data = [];
            $fromarray = explode(':', $filter->getFrom());
            $toarray = explode(':', $filter->getTo());
            $fieldName = $this->fieldMapper->getFieldName($filter->getField());
            for ($i=0; $i<count($fromarray); $i++) {
                $filterQuery['range'][$fieldName]['gte'] = $fromarray[$i];
                $filterQuery['range'][$fieldName]['lte'] = $toarray[$i];
                $data[] = $filterQuery;
            }
            return $data;
            //end
        } else {
            $filterQuery = [];
            $fieldName = $this->fieldMapper->getFieldName($filter->getField());
            if ($filter->getFrom()) {
                $filterQuery['range'][$fieldName]['gte'] = $filter->getFrom();
            }
            if ($filter->getTo()) {
                $filterQuery['range'][$fieldName]['lte'] = $filter->getTo();
            }
            return [$filterQuery];
        }
    }
}
