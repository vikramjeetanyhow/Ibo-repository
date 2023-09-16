<?php

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Ibo\HomePage\Model\HomeBestdealFactory;
use Psr\Log\LoggerInterface;
use Zend_Db_Expr;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Best Deal Data provider
 */
class BestdealProducts
{
    /** @var \Magento\Framework\Stdlib\DateTime\DateTime  */
    protected $dateTime;

    /** @var \Ibo\HomePage\Model\HomeBestdealFactory  */
    protected $homeBestdealFactory;

    private $logger;

    public function __construct(
        LoggerInterface $logger,
        HomeBestdealFactory $homeBestdealFactory,
        TimezoneInterface $dateTime       
    ) {        
        $this->homeBestdealFactory = $homeBestdealFactory;
        $this->logger = $logger;
        $this->dateTime = $dateTime;
    }
    
    public function getBestDealSkus($groupType)
    {
        $productSku=[];
        $date = $this->dateTime->date();
        $gmtDate = $date->format('Y-m-d H:i:s');     
        try {            
            $collection = $this->homeBestdealFactory->create()->getCollection()
                        ->addFieldToFilter('customer_group', ['finset' => $groupType])                        
                        ->addFieldToFilter(
                                'from_date',
                                ['lteq' => $gmtDate]
                            )
                        ->addFieldToFilter(
                                'to_date',
                                [
                                    'or' => [
                                        0 => ['gteq' => $gmtDate],
                                        1 => ['is' => new Zend_Db_Expr('null')],
                                    ]
                                ]
                            );
            foreach ($collection->getData() as $value) { 
                $productSku[] = (string) $value['sku'];
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e->getMessage());             
        }
        return array_unique($productSku);
    }
}
