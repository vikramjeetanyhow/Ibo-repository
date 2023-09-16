<?php

namespace Embitel\ProductExport\Model;

use Embitel\ProductExport\Api\Data\ProductExportInterface;
use Magento\Framework\Model\AbstractModel;

class EboExport extends AbstractModel implements ProductExportInterface
{


    protected function _construct()
    {
        $this->_init(ResourceModel\EboExport::class);
    }

    /**
     * @return int
     */
    public function getHistoryId(): int
    {
        return $this->getData(SELF::HISTORY_ID);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->getData(self::REQUEST_TYPE);
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->getData(self::FILENAME);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @param string $type
     * @return ProductExportInterface
     */
    public function setType(string $type): ProductExportInterface
    {
        return $this->setData(self::REQUEST_TYPE, $type);
    }

    /**
     * @param string $filename
     * @return ProductExportInterface
     */
    public function setFileName(string $filename): ProductExportInterface
    {
        return $this->setData(self::FILENAME, $filename);
    }

    /**
     * @param string $status
     * @return ProductExportInterface
     */
    public function setStatus(string $status): ProductExportInterface
    {
        return $this->setData(self::STATUS, $status);
    }


    public function getFailureCount(): string
    {
        return $this->getData(self::FAILURE_LIMIT);
    }

    /**
     * @param string $value
     * @return ProductExportInterface
     */
    public function setFailureCount(string $value): ProductExportInterface
    {
        return $this->setData(self::FAILURE_LIMIT, $value);
    }
}