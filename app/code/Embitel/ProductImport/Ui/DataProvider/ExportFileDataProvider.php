<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\ProductImport\Ui\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Embitel\ProductImport\Model\ResourceModel\EboImport\CollectionFactory;

/**
 * Data provider for export grid.
 */
class ExportFileDataProvider extends DataProvider
{
    /**
     * @var \Embitel\ProductImport\Model\EboImportFactory
     */
    protected $eboCollection;

    /**
     *
     * @var TimezoneInterface
     */
    protected $timezone;

    

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param \Magento\Framework\Api\Search\ReportingInterface $reporting
     * @param \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\Api\FilterBuilder $filterBuilder     
     * @param array $meta
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        \Magento\Framework\Api\Search\ReportingInterface $reporting,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        TimezoneInterface $timezone,
        CollectionFactory $eboCollection,       
        array $meta = [],
        array $data = []
    ) {
        
        $this->eboCollection = $eboCollection;
        $this->timezone = $timezone;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * Returns data for grid.
     *
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function getData()
    {   

        $date = date('Y-m-d', strtotime(
            "-7 days",
            strtotime($this->timezone->date()->format('Y-m-d H:i:s'))
        ));
        $collection = $this->eboCollection->create()
                ->addFieldToSelect(['success_filename', 'odoo_filename', 'failure_filename', 'is_bulk_upload', 'bulk_upload_status', 'upload_file_name'])
                ->addFieldToFilter('created_at', ['gt' => $date])
                ->setOrder('created_at','DESC');
        $result = [];
        if($collection->getSize() > 0) {
            foreach ($collection->getData() as $key=>$data) {
                $result['items'][$key] = $data;
            }
        }else{
            $emptyResponse = ['items' => [], 'totalRecords' => 0];            
            return $emptyResponse;            
        }

        $paging = $this->request->getParam('paging');
        $pageSize = (int) ($paging['pageSize'] ?? 0);
        $pageCurrent = (int) ($paging['current'] ?? 0);
        $pageOffset = ($pageCurrent - 1) * $pageSize;
        //$result['totalRecords'] = count($result['items']);
        $result['totalRecords'] = count($result['items']);
        $result['items'] = array_slice($result['items'], $pageOffset, $pageSize);        
        return $result;        
    }
}
