<?php

namespace Embitel\ProductExport\Api\Data;

use DateTimeInterface;
use Embitel\ProductExport\Model\ProductExportHelper;

interface ProductExportInterface
{

    const HISTORY_ID = 'history_id';

    const REQUEST_TYPE = 'type';

    const FILENAME = 'filename';

    const STATUS = 'status';

    const FAILURE_LIMIT = 'failure_hits';

    /**
     * @return int
     */
    public function getHistoryId(): int;

    /**
     * @return DateTimeInterface
     */
    public function getType(): string;

    /**
     * @return string
     */
    public function getFilename(): string;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @return string
     */
    public function getFailureCount(): string;

    /**
     * @param string $type
     * @return ProductExportInterface
     */
    public function setType(string $type): ProductExportInterface;

    /**
     * @param string $filename
     * @return ProductExportInterface
     */
    public function setFileName(string $filename): ProductExportInterface;

    /**
     * @param string $value
     * @return ProductExportHelper
     */
    public function setFailureCount(string $value): ProductExportInterface;

    /**
     * @param string $type
     * @return ProductExportInterface
     */
    public function setStatus(string $status): ProductExportInterface;

}