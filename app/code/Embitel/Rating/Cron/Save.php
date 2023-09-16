<?php

namespace Embitel\Rating\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\StoreRepository;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Catalog\Model\Product\Attribute\Repository as AttributeRepository;

class Save
{

    const RATING_ONE_STAR   = '1 Star';
    const RATING_TWO_STAR   = '2 Star';
    const RATING_THREE_STAR = '3 Star';
    const RATING_FOUR_STAR  = '4 Star';
    const RATING_FIVE_STAR  = '5 Star';

    /**
     * @var Rate
     */
    protected $_storeRepository;
    
    /**
     * @var IndexerRegistry
     */
    private $indexerRegistry;

    /**
     * @var AttributeRepository
     */
    protected $attributeRepository;

    /**
     * @var string[]
     */
    private $indexerList;
    
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $date,
        StoreRepository $storeRepository,
        AttributeRepository $attributeRepository,
        IndexerRegistry $indexerRegistry
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->_date =  $date;
        $this->_storeRepository = $storeRepository;
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->indexerRegistry = $indexerRegistry;
        $this->attributeRepository = $attributeRepository;
    }

    public function execute()
    {
        $ratingAttributeTable = $this->resourceConnection->getTableName('catalog_product_entity_int');
        $selectRatingAttribute = $this->connection->select()
                  ->from([$this->resourceConnection->getTableName('eav_attribute')], ['attribute_id'])
                  ->where('attribute_code = ?', 'ratings');
        
        $rsRatingAttribute = $this->connection->fetchCol($selectRatingAttribute);
        if (!isset($rsRatingAttribute[0])) {
            return;
        }
        $attributeId = $rsRatingAttribute[0];
        $productIds = [];
        $stores = $this->_storeRepository->getList();
        foreach ($stores as $store) {
            $storeId = $store["store_id"];
            $ratingData = $this->loadRatingData($storeId);
            if (count($ratingData) > 0) {
                foreach ($ratingData as $_rating) {
                    if ($_rating['rating_summary'] > 0) {
                        $data = [];
                        $data['value'] = $this->getRatingStar($_rating['rating_summary']);
                        $data['entity_id'] = $_rating['product_id'];
                        $data['store_id'] = $storeId;
                        $data['attribute_id'] = $attributeId;
                        $productIds[] = $_rating['product_id'];                        
                        $this->connection->insertOnDuplicate($ratingAttributeTable, $data, ['value']);
                    }
                }
            }
        }
        
        if (count($productIds) > 0) {
            try {
                $productIds = array_unique($productIds);
               
                $indexerList = ['catalog_product_flat','catalog_product_attribute','catalogsearch_fulltext'];
                if (!empty($productIds)) {
                    foreach ($indexerList as $indexerName) {
                        $indexer = $this->indexerRegistry->get($indexerName);
                        if (!$indexer->isScheduled()) {
                             $indexer->reindexList($productIds);
                        }
                    }
                }
            } catch (\Exception $ex) {
                $this->saveLog($ex->getMessage());
            }
        }
    }
    
    /**
     * Load rating data for a list of product ids and a given store.
     *
     * @param integer $storeId    Store id.
     * @param array   $productIds Product ids list.
     *
     * @return array
     */
    public function loadRatingData($storeId)
    {
        $request = $this->_objectManager->create(\Magento\Framework\App\Request\Http::class);
        $all = $request->getParam("all","");
        $todayDate = $this->_date->date()->format('Y-m-d');
        $selectRating = $this->connection->select()
                  ->from([$this->resourceConnection->getTableName('review')], ['entity_pk_value']);
                if ($all == '') {
                    $selectRating->where('DATE_FORMAT(created_at,"%Y-%m-%d") = ?', $todayDate);
                }                  
                $selectRating
                  ->where('status_id = ?', 1)
                  ->group('entity_pk_value');
       
        $productIds = $this->connection->fetchCol($selectRating);
        
            $select = $this->connection->select()
            ->from(
                ['res' => $this->resourceConnection->getTableName('review_entity_summary')],
                [
                    'entity_pk_value as product_id',
                    'reviews_count','rating_summary'
                ]
            )
            ->where('res.store_id = ?', $storeId)
            ->where('res.entity_pk_value IN(?)', $productIds)
            ->group('entity_pk_value');

        return $this->connection->fetchAll($select);
    }

    public function getRatingStar($percentage)
    { 
      $value='';
      if ($percentage > 0 && $percentage <=20) {
          $value = $this->getAttributeOptionsValue(self::RATING_ONE_STAR);
      } elseif ($percentage > 20 && $percentage <=40) {
          $value = $this->getAttributeOptionsValue(self::RATING_TWO_STAR);
      } elseif ($percentage > 40 && $percentage <=60) {
          $value = $this->getAttributeOptionsValue(self::RATING_THREE_STAR);
      } elseif ($percentage > 60 && $percentage <=80) {
          $value = $this->getAttributeOptionsValue(self::RATING_FOUR_STAR);
      } elseif ($percentage > 80 && $percentage <=100) {
          $value = $this->getAttributeOptionsValue(self::RATING_FIVE_STAR);
      }
      
      return $value;
    }

    /**
     * Get attribute options of dropdown attribute.
     *
     * @param type $attributeCode
     */
    protected function getAttributeOptionsValue($attributelabel)
    {
        $attribute = $this->getAttribute('ratings');
        $options = $attribute->getOptions();
        foreach ($options as $option) {
            if (trim($option->getLabel()) == trim($attributelabel)) {
                return trim($option->getValue());
            }
        }
    }
    
    /**
     * Get attribute by code
     *
     * @param type $attributeCode
     * @return type
     */
    protected function getAttribute($attributeCode)
    {
        try {
            return $this->attributeRepository->get($attributeCode);
        } catch (\Exception $ex) {
            //$this->productFieldProcessor->log($attributeCode . ": Attribute not exist. Error:" . $ex->getMessage());
            return null;
        }
    }
    /**
     * Save log message
     * @param string $message
     *
     * @return void
     */
    public function saveLog($message = '')
    {
        if ($message != '') {
            $filename = 'rating.log';
            $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/'.$filename);
            $logger = new \Zend\Log\Logger();
            $logger->addWriter($writer);
            $logger->info($message);
        }
    }
}
