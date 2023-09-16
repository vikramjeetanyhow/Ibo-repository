<?php

namespace Embitel\CatalogGraphQl\Model\Resolver\DataProvider;

use Ibo\HomePage\Model\HomeCategoriesFactory;
use Magento\Framework\GraphQl\Query\Uid;
use Zend_Db_Expr;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
/**
 * Home page Top categories and Top Brands Data provider
 */
class HomePage
{
    /** @var \Magento\Framework\Stdlib\DateTime\DateTime  */
    protected $dateTime;

    /** @var \Magento\Framework\GraphQl\Query\Uid  */
    protected $uidEncoder;

    /** @var \Ibo\HomePage\Model\HomeCategoriesFactory  */
    protected $homeCategoriesFactory;

    private $logger;

    public function __construct(
        LoggerInterface $logger,
        Uid $uidEncoder,
        TimezoneInterface $dateTime,
        HomeCategoriesFactory $homeCategoriesFactory
    ) {
        $this->homeCategoriesFactory = $homeCategoriesFactory;
        $this->uidEncoder = $uidEncoder;
        $this->logger = $logger;
        $this->dateTime = $dateTime;
    }

    public function getHomePageData($type, $groupType, $displayZone)
    {
        $catIds=[];
        $date = $this->dateTime->date();
        $gmtDate = $date->format('Y-m-d H:i:s');        
        try {            
            $collection = $this->homeCategoriesFactory->create()->getCollection()
                        ->addFieldToFilter('customer_group', ['finset' => $groupType])
                        ->addFieldToFilter('type', $type)
                        ->addFieldToFilter(
                                'from_date',
                                ['lteq' => $gmtDate]
                            )
                        ->addFieldToFilter(
                                'to_date',
                                [
                                    'or' => [
                                        0 => ['gteq' =>  $gmtDate],
                                        1 => ['is' => new Zend_Db_Expr('null')],
                                    ]
                                ]
                            );
            if(!is_null($displayZone)){
                $collection->addFieldToFilter('display_zone', ['in' => [$displayZone]]);
            }
            foreach ($collection->getData() as $value) { 
                $catIds[] = $this->uidEncoder->encode((string) $value['category_id']);
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->critical($e->getMessage());             
        }
        return $catIds;
    }
}
