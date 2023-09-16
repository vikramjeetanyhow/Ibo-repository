<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Embitel\OrderStatus\Ui\DataProvider;

use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Data provider for export grid.
 */
class OrderRepushDataProvider extends DataProvider
{
    /**
     * @var \Embitel\OrderStatus\Model\EboImportFactory
     */
    protected $eboCollection;

    /**
     *
     * @var TimezoneInterface
     */
    protected $timezone;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        \Magento\Framework\Api\Search\ReportingInterface $reporting,
        \Magento\Framework\Api\Search\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        TimezoneInterface $timezone,     
        array $meta = [],
        array $data = []
    ) {
        $this->timezone = $timezone;
        $this->resource = $resourceConnection;
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
        // $date = date('Y-m-d', strtotime(
        //     "-7 days",
        //     strtotime($this->timezone->date()->format('Y-m-d H:i:s'))
        // ));
        $connection = $this->resource->getConnection();
        $omsOrdersHistoryTable = $this->resource->getTableName('ibo_oms_orders_repush_history');
        $adminUserTable = $this->resource->getTableName('admin_user');
        $collection = $connection->query("SELECT oht.*, CONCAT(aut.firstname, ' ', aut.lastname) AS admin_name, aut.email as admin_email FROM $omsOrdersHistoryTable AS oht LEFT JOIN $adminUserTable AS aut ON(oht.admin_id = aut.user_id) ORDER BY oht.date_added DESC")->fetchAll();
        $result = [];
        if(!empty($collection)) {
            foreach ($collection as $key=>$data) {
                $data['date_added'] = $this->timezone->date(new \DateTime(
                    $data['date_added']))->format('Y-m-d h:i:s A');
                $result['items'][$key] = $data;
            }
        } else {
            $emptyResponse = ['items' => [], 'totalRecords' => 0];            
            return $emptyResponse;            
        }
        $paging = $this->request->getParam('paging');
        $pageSize = (int) ($paging['pageSize'] ?? 0);
        $pageCurrent = (int) ($paging['current'] ?? 0);
        $pageOffset = ($pageCurrent - 1) * $pageSize;
    
        $result['totalRecords'] = count($result['items']);
        $result['items'] = array_slice($result['items'], $pageOffset, $pageSize);       
       
        return $result;        
    }
}
