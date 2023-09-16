<?php

namespace Embitel\ProductExport\Ui\DataProvider;


use Embitel\ProductExport\Model\ResourceModel\EboExport\CollectionFactory;
use Embitel\ProductExport\Model\ResourceModel\EboExport\Collection;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ExportFileDataProvider extends AbstractDataProvider
{
    private CollectionFactory $collectionFactory;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param Collection $collection
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    )
    {
        $this->collection = $collectionFactory->create();

        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );


    }



}