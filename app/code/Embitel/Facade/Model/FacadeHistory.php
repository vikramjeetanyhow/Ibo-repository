<?php

namespace Embitel\Facade\Model;

use Embitel\Facade\Api\Data\FacadeHistoryInterface;
use Magento\Framework\Model\AbstractModel;

class FacadeHistory extends AbstractModel implements FacadeHistoryInterface
{

    public function _construct()
    {
        $this->_init(\Embitel\Facade\Model\ResourceModel\FacadeHistory::class);
    }

    /**
     * @inheirtDoc
     */
    public function getSku(): ?string
    {
        return $this->getData(self::SKU);
    }

    /**
     * @inheirtDoc
     */
    public function getHits(): ?int
    {
        return $this->getData(self::HITS);
    }

    /**
     * @inheirtDoc
     */
    public function setSku(string $sku): FacadeHistoryInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    /**
     * @inheirtDoc
     */
    public function setHits(int $count): FacadeHistoryInterface
    {
        return $this->setData(self::HITS, $count);
    }
}
