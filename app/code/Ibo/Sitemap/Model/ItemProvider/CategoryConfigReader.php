<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Sitemap\Model\ItemProvider;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;

class CategoryConfigReader implements ConfigReaderInterface
{
    /**#@+
     * Xpath config settings
     */
    const XML_PATH_CHANGE_FREQUENCY = 'sitemap/category/changefreq';
    const XML_PATH_CHANGE_FREQUENCY_BRAND = 'sitemap/category/changefreqbrand';
    const XML_PATH_PRIORITY = 'sitemap/category/priority';
    const XML_PATH_PRIORITY_level2 = 'sitemap/category/prioritylevel2';
    const XML_PATH_PRIORITY_level3 = 'sitemap/category/prioritylevel3';
    const XML_PATH_PRIORITYBRANONLY = 'sitemap/category/prioritybrand';
    /**#@-*/

    /**
     * Scope config
     *
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * CategoryItemResolverConfigReader constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriorityBrand($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PRIORITYBRANONLY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PRIORITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPrioritylevel2($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PRIORITY_level2,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getPrioritylevel3($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_PRIORITY_level3,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getChangeFrequency($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_CHANGE_FREQUENCY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getChangeFrequencyBrandUrls($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_CHANGE_FREQUENCY_BRAND,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
