<?php
namespace Anyhow\SupermaxPos\Model\Outlet\Attribute\Source;

class Products implements \Magento\Framework\Option\ArrayInterface {

    protected $_productCollectionFactory;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
    ) {
        $this->_productCollectionFactory = $productCollectionFactory;
    }

    public function toOptionArray()
    {
        $collection = $this->_productCollectionFactory->create();

        $options = [];

        foreach ($collection as $product) {
            $options[] = ['label' => $product->getSku(), 'value' => $product->getId()];
        }

        return $options;
    }

}