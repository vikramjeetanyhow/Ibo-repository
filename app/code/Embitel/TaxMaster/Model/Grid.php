<?php

/**
 * TaxMaster Grid Model.
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Model;

use Embitel\TaxMaster\Api\Data\GridInterface;

class Grid extends \Magento\Framework\Model\AbstractModel implements GridInterface
{
    /**
     * CMS page cache tag.
     */
    const CACHE_TAG = 'embitel_tax_master';

    /**
     * @var string
     */
    protected $_cacheTag = 'embitel_tax_master';

    /**
     * Prefix of model events names.
     *
     * @var string
     */
    protected $_eventPrefix = 'embitel_tax_master';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init(
            \Embitel\TaxMaster\Model\ResourceModel\Grid::class
        );
    }
    /**
     * Get EntityId.
     *
     * @return int
     */
    public function getEntityId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    /**
     * Set EntityId.
     *
     * @param string $entityId
     */
    public function setEntityId($entityId)
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * Get Title.
     *
     * @return varchar
     */
    public function getTitle()
    {
        return $this->getData(self::TITLE);
    }

    /**
     * Set Title.
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * Get getContent.
     *
     * @return varchar
     */
    public function getContent()
    {
        return $this->getData(self::CONTENT);
    }

    /**
     * Set Content.
     *
     * @param string $content
     */
    public function setContent($content)
    {
        return $this->setData(self::CONTENT, $content);
    }

    /**
     * Get PublishDate.
     *
     * @return varchar
     */
    public function getPublishDate()
    {
        return $this->getData(self::PUBLISH_DATE);
    }

    /**
     * Set PublishDate.
     *
     * @param string $publishDate
     */
    public function setPublishDate($publishDate)
    {
        return $this->setData(self::PUBLISH_DATE, $publishDate);
    }

    /**
     * Get IsActive.
     *
     * @return varchar
     */
    public function getIsActive()
    {
        return $this->getData(self::IS_ACTIVE);
    }

    /**
     * Set IsActive.
     *
     * @param string $isActive
     */
    public function setIsActive($isActive)
    {
        return $this->setData(self::IS_ACTIVE, $isActive);
    }

    /**
     * Get UpdatedAt.
     *
     * @return varchar
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Set UpdatedAt.
     *
     * @param string $updateTime
     */
    public function setUpdatedAt($updateTime)
    {
        return $this->setData(self::UPDATED_AT, $updateTime);
    }

    /**
     * Get CreatedAt.
     *
     * @return varchar
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * Set CreatedAt.
     *
     * @param string $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
