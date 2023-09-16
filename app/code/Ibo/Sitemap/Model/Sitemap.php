<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Ibo\Sitemap\Model;

use Magento\Config\Model\Config\Reader\Source\Deployed\DocumentRoot;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Robots\Model\Config\Value;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\ResourceModel\Sitemap as SitemapResource;
use Magento\Sitemap\Model\SitemapConfigReaderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Sitemap model.
 *
 * @method string getSitemapType()
 * @method \Magento\Sitemap\Model\Sitemap setSitemapType(string $value)
 * @method string getSitemapFilename()
 * @method \Magento\Sitemap\Model\Sitemap setSitemapFilename(string $value)
 * @method string getSitemapPath()
 * @method \Magento\Sitemap\Model\Sitemap setSitemapPath(string $value)
 * @method string getSitemapTime()
 * @method \Magento\Sitemap\Model\Sitemap setSitemapTime(string $value)
 * @method int getStoreId()
 * @method \Magento\Sitemap\Model\Sitemap setStoreId(int $value)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 * @since 100.0.2
 */
class Sitemap extends \Magento\Sitemap\Model\Sitemap
{
    const OPEN_TAG_KEY = 'start';

    const CLOSE_TAG_KEY = 'end';

    const INDEX_FILE_PREFIX = 'sitemap';

    const TYPE_INDEX = 'sitemap';

    const TYPE_URL = 'url';

    private const ROOT_DIRECTORY = 'sitemap';

    /**
     * Last mode date min value
     */
    const LAST_MOD_MIN_VAL = '0000-01-01 00:00:00';

    /**
     * Real file path
     *
     * @var string
     */
    protected $_filePath;

    /**
     * Sitemap items
     *
     * @var array
     */
    protected $_sitemapItems = [];

    /**
     * Current sitemap increment
     *
     * @var int
     */
    protected $_sitemapIncrement = 0;

    /**
     * Sitemap start and end tags
     *
     * @var array
     */
    protected $_tags = [];

    /**
     * Number of lines in sitemap
     *
     * @var int
     */
    protected $_lineCount = 0;

    /**
     * Current sitemap file size
     *
     * @var int
     */
    protected $_fileSize = 0;

    /**
     * New line possible symbols
     *
     * @var array
     */
    private $_crlf = ["win" => "\r\n", "unix" => "\n", "mac" => "\r"];

    /**
     * @var \Magento\Framework\Filesystem\Directory\Write
     */
    protected $_directory;

    /**
     * @var \Magento\Framework\Filesystem\File\Write
     */
    protected $_stream;

    /**
     * Sitemap data
     *
     * @var \Magento\Sitemap\Helper\Data
     */
    protected $_sitemapData;

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $_escaper;

    /**
     * @var \Magento\Sitemap\Model\ResourceModel\Catalog\CategoryFactory
     */
    protected $_categoryFactory;

    /**
     * @var \Magento\Sitemap\Model\ResourceModel\Catalog\ProductFactory
     */
    protected $_productFactory;

    /**
     * @var \Magento\Sitemap\Model\ResourceModel\Cms\PageFactory
     */
    protected $_cmsFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $_dateModel;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    /**
     * @var \Magento\Framework\Stdlib\DateTime
     */
    protected $dateTime;

    /**
     * @inheritdoc
     *
     * @since 100.1.5
     */
    protected $_cacheTag = [Value::CACHE_TAG];

    /**
     * Item resolver
     *
     * @var ItemProviderInterface
     */
    private $itemProvider;

    /**
     * Sitemap config reader
     *
     * @var SitemapConfigReaderInterface
     */
    private $configReader;

    /**
     * Sitemap Item Factory
     *
     * @var \Magento\Sitemap\Model\SitemapItemInterfaceFactory
     */
    private $sitemapItemFactory;

    /**
     * Last mode min timestamp value
     *
     * @var int
     */
    private $lastModMinTsVal;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var DocumentRoot
     */
    private $documentRoot;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * Initialize dependencies.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Escaper $escaper
     * @param \Magento\Sitemap\Helper\Data $sitemapData
     * @param \Magento\Framework\Filesystem $filesystem
     * @param ResourceModel\Catalog\CategoryFactory $categoryFactory
     * @param ResourceModel\Catalog\ProductFactory $productFactory
     * @param ResourceModel\Cms\PageFactory $cmsFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $modelDate
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Stdlib\DateTime $dateTime
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @param DocumentRoot|null $documentRoot
     * @param ItemProviderInterface|null $itemProvider
     * @param SitemapConfigReaderInterface|null $configReader
     * @param \Magento\Sitemap\Model\SitemapItemInterfaceFactory|null $sitemapItemFactory
     * @param ScopeConfigInterface $scopeConfigInterface
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Escaper $escaper,
        \Magento\Sitemap\Helper\Data $sitemapData,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Sitemap\Model\ResourceModel\Catalog\CategoryFactory $categoryFactory,
        \Magento\Sitemap\Model\ResourceModel\Catalog\ProductFactory $productFactory,
        \Magento\Sitemap\Model\ResourceModel\Cms\PageFactory $cmsFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $modelDate,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        \Magento\Config\Model\Config\Reader\Source\Deployed\DocumentRoot $documentRoot = null,
        ItemProviderInterface $itemProvider = null,
        SitemapConfigReaderInterface $configReader = null,
        \Magento\Sitemap\Model\SitemapItemInterfaceFactory $sitemapItemFactory = null,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->_escaper = $escaper;
        $this->_sitemapData = $sitemapData;
        $this->filesystem = $filesystem;
        $this->_directory = $filesystem->getDirectoryWrite(DirectoryList::PUB);
        $this->_categoryFactory = $categoryFactory;
        $this->_productFactory = $productFactory;
        $this->_cmsFactory = $cmsFactory;
        $this->_dateModel = $modelDate;
        $this->_storeManager = $storeManager;
        $this->_request = $request;
        $this->dateTime = $dateTime;
        $this->itemProvider = $itemProvider ?: ObjectManager::getInstance()->get(ItemProviderInterface::class);
        $this->configReader = $configReader ?: ObjectManager::getInstance()->get(SitemapConfigReaderInterface::class);
        $this->sitemapItemFactory = $sitemapItemFactory ?: ObjectManager::getInstance()->get(
            \Magento\Sitemap\Model\SitemapItemInterfaceFactory::class
        );
        $this->scopeConfigInterface  = $scopeConfigInterface;

        parent::__construct($context, $registry, $escaper, $sitemapData, $filesystem, $categoryFactory, $productFactory, $cmsFactory, $modelDate, $storeManager, $request, $dateTime);
    }

    /**
     * Init model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(SitemapResource::class);
    }

 
    /**
     * Get sitemap row
     *
     * @param string $url
     * @param null|string $lastmod
     * @param null|string $changefreq
     * @param null|string $priority
     * @param null|array|\Magento\Framework\DataObject $images
     * @return string
     * Sitemap images
     * @see http://support.google.com/webmasters/bin/answer.py?hl=en&answer=178636
     *
     * Sitemap PageMap
     * @see http://support.google.com/customsearch/bin/answer.py?hl=en&answer=1628213
     */
    protected function _getSitemapRow($url, $lastmod = null, $changefreq = null, $priority = null, $images = null)
    {
        $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/sitemap.log');
        $logger = new \Laminas\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('=======Start========');

        $storeScope = ScopeInterface::SCOPE_STORE;
        $websiteUrl = $this->scopeConfigInterface->getValue(
            'sitemap/website_url_selection/web_url',
            $storeScope
        ); 
        $logger->info('----');

        if($websiteUrl !='')
        {
           $url = $websiteUrl.'/'.$url;
           $logger->info('IF:'.$url);
        }
        else{
            $url = $this->_getUrl($url); 
            $logger->info('ELSE:'.$url);
        }
        $logger->info('Website Url: '.$url);
      
        $row = '<loc>' . $this->_escaper->escapeUrl($url) . '</loc>';
        if ($lastmod) {
            $row .= '<lastmod>' . $this->_getFormattedLastmodDate($lastmod) . '</lastmod>';
        }
        if ($changefreq) {
            $row .= '<changefreq>' . $this->_escaper->escapeHtml($changefreq) . '</changefreq>';
        }
        if ($priority) {
            $row .= sprintf('<priority>%0.2f</priority>', $this->_escaper->escapeHtml($priority));
        }
        if ($images) {
            // Add Images to sitemap
            foreach ($images->getCollection() as $image) {
                $row .= '<image:image>';
                 $row .= '<image:loc>' . $this->_escaper->escapeUrl($image->getUrl()) . '</image:loc>';
                $row .= '<image:title>' . $this->escapeXmlText($images->getTitle()) . '</image:title>';
                if ($image->getCaption()) {
                    $row .= '<image:caption>' . $this->escapeXmlText($image->getCaption()) . '</image:caption>';
                }
                $row .= '</image:image>';
            }
            // Add PageMap image for Google web search
            $images->getThumbnail();
            
            $row .= '<PageMap xmlns="http://www.google.com/schemas/sitemap-pagemap/1.0"><DataObject type="thumbnail">';
            $row .= '<Attribute name="name" value="' . $this->_escaper->escapeHtmlAttr($images->getTitle()) . '"/>';
            $row .= '<Attribute name="src" value="' . $this->_escaper->escapeUrl($images->getThumbnail()) . '"/>';
            $row .= '</DataObject></PageMap>';
        }
        return '<url>' . $row . '</url>';
    }

 /**
     * Escape string for XML context.
     *
     * @param string $text
     * @return string
     */
    private function escapeXmlText(string $text): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $fragment = $doc->createDocumentFragment();
        $fragment->appendChild($doc->createTextNode($text));
        return $doc->saveXML($fragment);
    }    

}
