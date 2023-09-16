<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Embitel\ProductFilter\Model;

use Magento\Search\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Stdlib\StringUtils as StdlibString;
use Magento\Search\Model\QueryFactory as SearchQueryFactory;

/**
 * @api
 * @since 100.0.2
 */
class QueryFactory
{
    /**
     * Query variable
     */
    const QUERY_VAR_NAME = 'q';

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * @var Query
     */
    private $query;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StdlibString
     */
    private $string;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Data
     */
    private $queryHelper;
    
    /**
     * @var SearchQueryFactory
     */
    private $searchQueryFactory;

    /**
     * @param Context $context
     * @param ObjectManagerInterface $objectManager
     * @param StdlibString $string
     * @param Data|null $queryHelper
     * @param SearchQueryFactory $searchQueryFactory
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        StdlibString $string,
        Data $queryHelper = null,
        SearchQueryFactory $searchQueryFactory
    ) {
        $this->request = $context->getRequest();
        $this->objectManager = $objectManager;
        $this->string = $string;
        $this->scopeConfig = $context->getScopeConfig();
        $this->queryHelper = $queryHelper === null ? $this->objectManager->get(Data::class) : $queryHelper;
        $this->searchQueryFactory = $searchQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function get($rawQueryText)
    {
            $maxQueryLength = $this->queryHelper->getMaxQueryLength();
            $minQueryLength = $this->queryHelper->getMinQueryLength();
            $preparedQueryText = $this->getPreparedQueryText($rawQueryText, $maxQueryLength);
            $query = $this->searchQueryFactory->create()->loadByQueryText($preparedQueryText);
        if (!$query->getId()) {
            $query->setQueryText($preparedQueryText);
        }
            $query->setIsQueryTextExceeded($this->isQueryTooLong($rawQueryText, $maxQueryLength));
            $query->setIsQueryTextShort($this->isQueryTooShort($rawQueryText, $minQueryLength));
            return $query;
    }

    /**
     * @param string $queryText
     * @param int|string $maxQueryLength
     * @return string
     */
    private function getPreparedQueryText($queryText, $maxQueryLength)
    {
        if ($this->isQueryTooLong($queryText, $maxQueryLength)) {
            $queryText = $this->string->substr($queryText, 0, $maxQueryLength);
        }
        return $queryText;
    }

    /**
     * @param string $queryText
     * @param int|string $maxQueryLength
     * @return bool
     */
    private function isQueryTooLong($queryText, $maxQueryLength)
    {
        return ($maxQueryLength !== '' && $this->string->strlen($queryText) > $maxQueryLength);
    }

    /**
     * @param string $queryText
     * @param int|string $minQueryLength
     * @return bool
     */
    private function isQueryTooShort($queryText, $minQueryLength)
    {
        return ($this->string->strlen($queryText) < $minQueryLength);
    }
}
