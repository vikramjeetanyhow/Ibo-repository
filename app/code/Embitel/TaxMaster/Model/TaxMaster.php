<?php
namespace Embitel\TaxMaster\Model;

use Embitel\TaxMaster\Api\TaxUpdateRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Framework\Model\AbstractModel;

class TaxMaster extends AbstractModel
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var Product\Action
     */
    protected $productAction;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param ProductAction $productAction
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        ProductAction $productAction
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productAction =  $productAction;
    }

    /**
     * Model construct that should be used for object initialization.
     */
    public function _construct()
    {
        $this->_init(
            \Embitel\TaxMaster\Model\ResourceModel\TaxMaster::class
        );
    }
}
