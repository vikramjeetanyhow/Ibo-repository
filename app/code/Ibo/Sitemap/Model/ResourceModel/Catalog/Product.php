<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ibo\Sitemap\Model\ResourceModel\Catalog;

use Magento\Catalog\Helper\Product as HelperProduct;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Eav\Api\AttributeRepositoryInterface;

/**
 * Sitemap resource product collection model
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 * @since 100.0.2
 */
class Product extends \Magento\Sitemap\Model\ResourceModel\Catalog\Product
{
    const NOT_SELECTED_IMAGE = 'no_selection';

    /**
     * Collection Zend Db select
     *
     * @var \Magento\Framework\DB\Select
     */
    protected $_select;

    /**
     * Attribute cache
     *
     * @var array
     */
    protected $_attributesCache = [];

    /**
     * @var \Magento\Catalog\Model\Product\Gallery\ReadHandler
     * @since 100.1.0
     */
    protected $mediaGalleryReadHandler;

    /**
     * Sitemap data
     *
     * @var \Magento\Sitemap\Helper\Data
     */
    protected $_sitemapData = null;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    protected $_productResource;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $_productVisibility;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    protected $_productStatus;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Gallery
     * @since 100.1.0
     */
    protected $mediaGalleryResourceModel;

    /**
     * @var \Magento\Catalog\Model\Product\Media\Config
     * @deprecated 100.2.0 unused
     */
    protected $_mediaConfig;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $productModel;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    private $catalogImageHelper;

    /**
     * @var UrlBuilder
     */
    private $imageUrlBuilder;

    /**
     * Scope Config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Eav Attribute
     *
     * @var \Magento\Eav\Api\AttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * Product constructor.
     *
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Sitemap\Helper\Data $sitemapData
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus
     * @param \Magento\Catalog\Model\ResourceModel\Product\Gallery $mediaGalleryResourceModel
     * @param \Magento\Catalog\Model\Product\Gallery\ReadHandler $mediaGalleryReadHandler
     * @param \Magento\Catalog\Model\Product\Media\Config $mediaConfig
     * @param string $connectionName
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Catalog\Helper\Image $catalogImageHelper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface|null $scopeConfig
     * @param UrlBuilder $urlBuilder
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\ResourceModel\Db\Context $context,
        \Magento\Sitemap\Helper\Data $sitemapData,
        \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus,
        \Magento\Catalog\Model\ResourceModel\Product\Gallery $mediaGalleryResourceModel,
        \Magento\Catalog\Model\Product\Gallery\ReadHandler $mediaGalleryReadHandler,
        \Magento\Catalog\Model\Product\Media\Config $mediaConfig,
        $connectionName = null,
        \Magento\Catalog\Model\Product $productModel = null,
        \Magento\Catalog\Helper\Image $catalogImageHelper = null,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig = null,
        UrlBuilder $urlBuilder = null,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->_productResource = $productResource;
        $this->_storeManager = $storeManager;
        $this->_productVisibility = $productVisibility;
        $this->_productStatus = $productStatus;
        $this->mediaGalleryResourceModel = $mediaGalleryResourceModel;
        $this->mediaGalleryReadHandler = $mediaGalleryReadHandler;
        $this->_mediaConfig = $mediaConfig;
        $this->_sitemapData = $sitemapData;
        $this->productModel = $productModel ?: ObjectManager::getInstance()->get(\Magento\Catalog\Model\Product::class);
        $this->catalogImageHelper = $catalogImageHelper;
        $this->imageUrlBuilder = $urlBuilder ?? ObjectManager::getInstance()->get(UrlBuilder::class);
        $this->catalogImageHelper = $catalogImageHelper ?: ObjectManager::getInstance()
            ->get(\Magento\Catalog\Helper\Image::class);
        $this->scopeConfig = $scopeConfig ?: ObjectManager::getInstance()
            ->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $this->attributeRepository = $attributeRepository;  

         parent::__construct($context, $sitemapData, $productResource, $storeManager, $productVisibility, $productStatus, $mediaGalleryResourceModel, $mediaGalleryReadHandler, $mediaConfig, $connectionName, $productModel, $catalogImageHelper, $scopeConfig, $urlBuilder);
    }

    /**
     * Construct
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('catalog_product_entity', 'entity_id');
    }

    /**
     * Add attribute to filter
     *
     * @param int $storeId
     * @param string $attributeCode
     * @param mixed $value
     * @param string $type
     *
     * @return \Magento\Framework\DB\Select|bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _addFilter($storeId, $attributeCode, $value, $type = '=')
    {
        if (!$this->_select instanceof \Magento\Framework\DB\Select) {
            return false;
        }

        switch ($type) {
            case '=':
                $conditionRule = '=?';
                break;
            case 'in':
                $conditionRule = ' IN(?)';
                break;
            default:
                return false;
        }

        $attribute = $this->_getAttribute($attributeCode);
        if ($attribute['backend_type'] == 'static') {
            $this->_select->where('e.' . $attributeCode . $conditionRule, $value);
        } else {
            $this->_joinAttribute($storeId, $attributeCode);
            if ($attribute['is_global']) {
                $this->_select->where('t1_' . $attributeCode . '.value' . $conditionRule, $value);
            } else {
                $ifCase = $this->getConnection()->getCheckSql(
                    't2_' . $attributeCode . '.value_id > 0',
                    't2_' . $attributeCode . '.value',
                    't1_' . $attributeCode . '.value'
                );
                $this->_select->where('(' . $ifCase . ')' . $conditionRule, $value);
            }
        }

        return $this->_select;
    }

    /**
     * Join attribute by code
     *
     * @param int $storeId
     * @param string $attributeCode
     * @param string $column Add attribute value to given column
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _joinAttribute($storeId, $attributeCode, $column = null)
    {
        $connection = $this->getConnection();
        $attribute = $this->_getAttribute($attributeCode);
        $linkField = $this->_productResource->getLinkField();
        $attrTableAlias = 't1_' . $attributeCode;
        $this->_select->joinLeft(
            [$attrTableAlias => $attribute['table']],
            "e.{$linkField} = {$attrTableAlias}.{$linkField}"
            . ' AND ' . $connection->quoteInto($attrTableAlias . '.store_id = ?', Store::DEFAULT_STORE_ID)
            . ' AND ' . $connection->quoteInto($attrTableAlias . '.attribute_id = ?', $attribute['attribute_id']),
            []
        );
        // Global scope attribute value
        $columnValue = 't1_' . $attributeCode . '.value';

        if (!$attribute['is_global']) {
            $attrTableAlias2 = 't2_' . $attributeCode;
            $this->_select->joinLeft(
                ['t2_' . $attributeCode => $attribute['table']],
                "{$attrTableAlias}.{$linkField} = {$attrTableAlias2}.{$linkField}"
                . ' AND ' . $attrTableAlias . '.attribute_id = ' . $attrTableAlias2 . '.attribute_id'
                . ' AND ' . $connection->quoteInto($attrTableAlias2 . '.store_id = ?', $storeId),
                []
            );
            // Store scope attribute value
            $columnValue = $this->getConnection()->getIfNullSql('t2_'  . $attributeCode . '.value', $columnValue);
        }

        // Add attribute value to result set if needed
        if (isset($column)) {
            $this->_select->columns(
                [
                    $column => $columnValue
                ]
            );
        }
    }

    /**
     * Get attribute data by attribute code
     *
     * @param string $attributeCode
     *
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getAttribute($attributeCode)
    {
        if (!isset($this->_attributesCache[$attributeCode])) {
            $attribute = $this->_productResource->getAttribute($attributeCode);

            $this->_attributesCache[$attributeCode] = [
                'entity_type_id' => $attribute->getEntityTypeId(),
                'attribute_id' => $attribute->getId(),
                'table' => $attribute->getBackend()->getTable(),
                'is_global' => $attribute->getIsGlobal() ==
                    \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'backend_type' => $attribute->getBackendType(),
            ];
        }
        return $this->_attributesCache[$attributeCode];
    }

    /**
     * Get product collection array
     *
     * @param null|string|bool|int|Store $storeId
     *
     * @return array|bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Db_Statement_Exception
     */
    public function getCollection($storeId)
    {
        $products = [];

        /* @var $store Store */
        $store = $this->_storeManager->getStore($storeId);
        if (!$store) {
            return false;
        }

        $attributeArr = $this->attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'esin');
        $esinAttributeId = $attributeArr->getAttributeId();

        $primaryattributeArr = $this->attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'base_image_custom');
        $primaryAttributeId = $primaryattributeArr->getAttributeId();

        $additionalattributeArr = $this->attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'media_gallery_custom');
        $additionalAttributeId = $additionalattributeArr->getAttributeId();

        $allowedChannelAttribute = $this->attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'allowed_channels');
        $allowedChannelAttributeId = $allowedChannelAttribute->getAttributeId();

        $publishAttribute = $this->attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'is_published');
        $publishAttributeId = $publishAttribute->getAttributeId();

        $slugAttribute = $this->attributeRepository->get(\Magento\Catalog\Model\Product::ENTITY, 'slug');
        $slugAttributeId = $slugAttribute->getAttributeId();
        
        $connection = $this->getConnection();
        $urlRewriteMetaDataCondition = '';
        if (!$this->isCategoryProductURLsConfig($storeId)) {
            $urlRewriteMetaDataCondition = ' AND url_rewrite.metadata IS NULL';
        }

        $this->_select = $connection->select()->from(
            ['e' => $this->getMainTable()],
            [$this->getIdFieldName(), $this->_productResource->getLinkField(), 'updated_at']
        )->joinLeft(
            ['url_rewrite' => $this->getTable('url_rewrite')],
            'e.entity_id = url_rewrite.entity_id AND url_rewrite.is_autogenerated = 1'
            . $urlRewriteMetaDataCondition
            . $connection->quoteInto(' AND url_rewrite.store_id = ?', $store->getId())
            . $connection->quoteInto(' AND url_rewrite.entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE),
            ['url' => 'request_path']
        )->joinInner(
            ['cpev' => $this->getTable('catalog_product_entity_varchar')],
            'e.entity_id = cpev.entity_id AND cpev.attribute_id ='. $esinAttributeId,
            ['esin' => 'cpev.value']
        )->joinLeft(
            ['cpv' => $this->getTable('catalog_product_entity_varchar')],
            'e.entity_id = cpv.entity_id AND cpv.attribute_id ='. $primaryAttributeId,
            ['base_image_custom' => 'cpv.value']
        )->joinLeft(
            ['cpt' => $this->getTable('catalog_product_entity_varchar')],
            'e.entity_id = cpt.entity_id AND cpt.attribute_id ='.  $slugAttributeId,
            ['slug' => 'cpt.value']
        )->joinLeft(
            ['cps' => $this->getTable('catalog_product_entity_text')],
            'e.entity_id = cps.entity_id AND cps.attribute_id ='. $additionalAttributeId,
            ['media_gallery_custom' => 'cps.value']
        )->joinInner(
            ['cpv1' => $this->getTable('catalog_product_entity_varchar')],
            'e.entity_id = cpv1.entity_id AND cpv1.attribute_id ='. $allowedChannelAttributeId. ' AND cpv1.value !="STORE"',
            []
        )->joinInner(
            ['cpv2' => $this->getTable('catalog_product_entity_int')],
            'e.entity_id = cpv2.entity_id AND cpv2.attribute_id ='. $publishAttributeId. ' AND cpv2.value = 1',
            []
        )->joinInner(
            ['csi' => $this->getTable('cataloginventory_stock_item')],
            'e.entity_id = csi.product_id AND csi.is_in_stock = 1',
            []
        )->joinInner(
            ['cpsl' => $this->getTable('catalog_product_super_link')],
            'e.entity_id = cpsl.product_id AND cpsl.parent_id != ""',
            []
        );
       // $this->_addFilter($store->getId(), 'visibility', $this->_productVisibility->getVisibleInSiteIds(), 'in');
        $this->_addFilter($store->getId(), 'status', $this->_productStatus->getVisibleStatusIds(), '=');
        // Join product images required attributes
        $imageIncludePolicy = $this->_sitemapData->getProductImageIncludePolicy($store->getId());
        if (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_NONE != $imageIncludePolicy) {
            $this->_joinAttribute($store->getId(), 'name', 'name');
            if (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_ALL == $imageIncludePolicy) {
                $this->_joinAttribute($store->getId(), 'thumbnail', 'thumbnail');
            } elseif (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_BASE == $imageIncludePolicy) {
                $this->_joinAttribute($store->getId(), 'image', 'image');
            }
        }
        $query = $connection->query($this->prepareSelectStatement($this->_select));
        while ($row = $query->fetch()) {
            $product = $this->_prepareProduct($row, $store->getId());
            $products[$product->getId()] = $product;
        }

        return $products;
    }

    /**
     * Prepare product
     *
     * @param array $productRow
     * @param int $storeId
     *
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareProduct(array $productRow, $storeId)
    {
        $string = strtolower((str_replace(" ", "-", $productRow['slug'])));
        $product = new \Magento\Framework\DataObject();
        $product['id'] = $productRow[$this->getIdFieldName()];
        $exp = explode('.html', $productRow['url']);
        //$productRow['url'] = 'catalog/product/view/id/' . $product->getId();
        $productRow['url'] = $string.'/p/'.$productRow['esin'];
        $product->addData($productRow);
        $this->_loadProductImages($product, $storeId);
        return $product;
    }

    /**
     * Load product images
     *
     * @param \Magento\Framework\DataObject $product
     * @param int $storeId
     * @return void
     */
    protected function _loadProductImages($product, $storeId)
    {
        $this->_storeManager->setCurrentStore($storeId);
        /** @var $helper \Magento\Sitemap\Helper\Data */
        $helper = $this->_sitemapData;
        $imageIncludePolicy = $helper->getProductImageIncludePolicy($storeId);

        $storeScope = ScopeInterface::SCOPE_STORE;
        $customImageEnabled = $this->scopeConfig->getValue(
            "core_media/service/use_custom_source",
            $storeScope
        );

        if($customImageEnabled) {
            $imagesCollection = [];

            if($product->getBaseImageCustom() != '') {
                $imagesCollection[] =  new \Magento\Framework\DataObject(
                                ['url' => $product->getBaseImageCustom()]
                            );

            }


            if($product->getMediaGalleryCustom() != ''){ 
                    $galleryImages = explode(',',$product->getMediaGalleryCustom());
                    foreach($galleryImages as $galleryImage) {
                        $imagesCollection[] = new \Magento\Framework\DataObject(
                            [
                                'url' => $galleryImage,
                            ]
                        );
                    }
                }

                if ($imagesCollection) {
                    // Determine thumbnail path
                    $thumbnail = ($product->getBaseImageCustom() != '')?$product->getBaseImageCustom():'';

                    $product->setImages(
                        new \Magento\Framework\DataObject(
                            ['collection' => $imagesCollection, 'title' => $product->getName(), 'thumbnail' => $thumbnail]
                        )
                    );
                }

             } else {

            // Get product images
            $imagesCollection = [];
            if (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_ALL == $imageIncludePolicy) {
                $imagesCollection = $this->_getAllProductImages($product, $storeId);

            } elseif (\Magento\Sitemap\Model\Source\Product\Image\IncludeImage::INCLUDE_BASE == $imageIncludePolicy &&
                $product->getImage() &&
                $product->getImage() != self::NOT_SELECTED_IMAGE
            ) {
                $imagesCollection = [
                    new \Magento\Framework\DataObject(
                        ['url' => $this->getProductImageUrl($product->getImage())]
                    ),
                ];
            }

            if ($imagesCollection) {
                // Determine thumbnail path
                $thumbnail = $product->getThumbnail();
                if ($thumbnail && $product->getThumbnail() != self::NOT_SELECTED_IMAGE) {
                    $thumbnail = $this->getProductImageUrl($thumbnail);
                } else {
                    $thumbnail = $imagesCollection[0]->getUrl();
                }

                $product->setImages(
                    new \Magento\Framework\DataObject(
                        ['collection' => $imagesCollection, 'title' => $product->getName(), 'thumbnail' => $thumbnail]
                    )
                );
            }
            
        }
    }

    /**
     * Get all product images
     *
     * @param \Magento\Framework\DataObject $product
     * @param int $storeId
     * @return array
     */
    protected function _getAllProductImages($product, $storeId)
    {
        $product->setStoreId($storeId);
        $gallery = $this->mediaGalleryResourceModel->loadProductGalleryByAttributeId(
            $product,
            $this->mediaGalleryReadHandler->getAttribute()->getId()
        );

        $imagesCollection = [];
        if ($gallery) {
            foreach ($gallery as $image) {
                $imagesCollection[] = new \Magento\Framework\DataObject(
                    [
                        'url' => $this->getProductImageUrl($image['file']),
                        'caption' => $image['label'] ? $image['label'] : $image['label_default'],
                    ]
                );
            }
        }
        return $imagesCollection;
    }

    /**
     * Get media config
     *
     * @return \Magento\Catalog\Model\Product\Media\Config
     * @deprecated 100.2.0 No longer used, as we're getting full image URL from getProductImageUrl method
     * @see getProductImageUrl()
     */
    protected function _getMediaConfig()
    {
        return $this->_mediaConfig;
    }

    /**
     * Allow to modify select statement with plugins
     *
     * @param \Magento\Framework\DB\Select $select
     * @return \Magento\Framework\DB\Select
     * @since 100.2.1
     */
    public function prepareSelectStatement(\Magento\Framework\DB\Select $select)
    {
        return $select.'AND (e.type_id = "simple")';
    }

    /**
     * Get product image URL from image filename
     *
     * @param string $image
     * @return string
     */
    private function getProductImageUrl($image)
    {
        return $this->imageUrlBuilder->getUrl($image, 'product_page_image_large');
    }

    /**
     * Return Use Categories Path for Product URLs config value
     *
     * @param null|string $storeId
     *
     * @return bool
     */
    private function isCategoryProductURLsConfig($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            HelperProduct::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
