<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Embitel\Quote\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\VersionControl\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\RelationComposite;
use Magento\Framework\Model\ResourceModel\Db\VersionControl\Snapshot;
use Magento\SalesSequence\Model\Manager;

/**
 * Quote resource model
 */
class Quote extends \Magento\Quote\Model\ResourceModel\Quote
{
    /**
     * Quickly check if quote exists
     *
     * Uses direct DB query due to performance reasons
     *
     * @param int $quoteId
     * @return bool
     */
    public function isExists(int $quoteId): bool
    {
        $connection = $this->getConnection();
        $mainTable = $this->getMainTable();
        $idFieldName = $this->getIdFieldName();

        $field = $connection->quoteIdentifier(sprintf('%s.%s', $mainTable, $idFieldName));
        $select = $connection->select()
            ->from($mainTable, [$idFieldName])
            ->where($field . '=?', $quoteId);

        return (bool)$connection->fetchOne($select);
    }
}