<?php

namespace Ibo\DynamicBlockScript\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class BlockResourceModel extends AbstractDb
{
    /**
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    protected $serializer;
    protected $request;
    /**
     * @SuppressWarnings(PHPMD.LongVariable)
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        SerializerInterface $serializer,
        $connectionName = null
    ) {
        $this->serializer = $serializer;
        $this->request = $request;
        parent::__construct($context, $connectionName);
    }
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _construct()
    {
        $this->_init('static_block_script', 'id');
    }

    /**
     * Process page data before saving
     *
     * @param AbstractModel $object
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if (!$this->getIsUniqueBlockToStores($object)) {
            throw new LocalizedException(
                __('A script for the static block already exists.')
            );
        }
        return $this;
    }

    /**
     * Check for unique of identifier of block to selected store(s).
     *
     * @param AbstractModel $object
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsUniqueBlockToStores(AbstractModel $object)
    {
        $select = $this->getConnection()->select()
            ->from(['chsp' => $this->getMainTable()])
            ->where('chsp.identifier = ?  ', $object->getData('identifier'));

        if ($object->getId()) {
            $select->where('chsp.id != ?', $object->getId());
        }

        if ($this->getConnection()->fetchRow($select)) {
            return false;
        }

        return true;
    }
}
