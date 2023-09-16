<?php
/**
 * Embitel Tax Master GridInterface.
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */

namespace Embitel\TaxMaster\Api\Data;

interface GridInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'id';
    const TITLE = 'title';
    const CONTENT = 'content';
    const PUBLISH_DATE = 'publish_date';
    const IS_ACTIVE = 'is_active';
    const UPDATED_AT = 'updated_at';
    const CREATED_AT = 'created_at';

   /**
    * Get EntityId.
    *
    * @return int
    */
    public function getEntityId();

   /**
    * Set EntityId.
    *
    * @param string $entityId
    */
    public function setEntityId($entityId);

   /**
    * Get Title.
    *
    * @return varchar
    */
    public function getTitle();

   /**
    * Set Title.
    *
    * @param string $title
    */
    public function setTitle($title);

   /**
    * Get Content.
    *
    * @return varchar
    */
    public function getContent();

   /**
    * Set Content.
    *
    * @param string $content
    */
    public function setContent($content);

   /**
    * Get Publish Date.
    *
    * @return varchar
    */
    public function getPublishDate();

   /**
    * Set PublishDate.
    *
    * @param string $publishDate
    */
    public function setPublishDate($publishDate);

   /**
    * Get IsActive.
    *
    * @return varchar
    */
    public function getIsActive();

   /**
    * Set StartingPrice.
    *
    * @param string $isActive
    */
    public function setIsActive($isActive);

   /**
    * Get UpdateTime.
    *
    * @return varchar
    */
    public function getUpdatedAt();

   /**
    * Set UpdatedAt.
    *
    * @param string $updateTime
    */
    public function setUpdatedAt($updateTime);

   /**
    * Get CreatedAt.
    *
    * @return varchar
    */
    public function getCreatedAt();

   /**
    * Set CreatedAt.
    *
    * @param  string $createdAt
    */
    public function setCreatedAt($createdAt);
}
