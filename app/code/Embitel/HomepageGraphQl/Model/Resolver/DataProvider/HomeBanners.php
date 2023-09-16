<?php

namespace Embitel\HomepageGraphQl\Model\Resolver\DataProvider;

use Embitel\Banner\Model\BannerFactory;
use Magento\Store\Model\StoreManagerInterface;
use Zend_Db_Expr;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
/**
 * Home Banner Data provider
 */
class HomeBanners
{

    /** @var \Magento\Store\Model\StoreManagerInterface  */
    protected $storeManager;

    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        TimezoneInterface $dateTime,
        BannerFactory $bannerFactory
    ) {
        $this->bannerFactory = $bannerFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->dateTime = $dateTime;
    }

    public function getBannersData($groupType,$displayZone)
    {
        $homeBannersData=[];
        $date = $this->dateTime->date();
        $gmtDate = $date->format('Y-m-d h:i:s');
        $bannerCount = $this->scopeConfig->getValue("banner_cate/cat_config/banner_count");
        $limit = ($bannerCount) ? $bannerCount : 5;
        try {            
            $collection = $this->bannerFactory->create()->getCollection()
                        ->addFieldToFilter('status', 1)
                        ->addFieldToFilter('customer_group', ['finset' => $groupType])
                        ->addFieldToFilter(
                            'from_date_time',
                            ['lteq' => $gmtDate]
                        )
                        ->addFieldToFilter(
                            'to_date_time',
                            [
                                'or' => [
                                    0 => ['gteq' => $gmtDate],
                                    1 => ['is' => new Zend_Db_Expr('null')],
                                ]
                            ]
                        )                     
                        ;
            if(!is_null($displayZone)){                
                if(!empty($displayZone['in'])){
                    $collection->addFieldToFilter('display_zone', ['in' => $displayZone['in']]);
                }else if($displayZone['eq']){                
                    $collection->addFieldToFilter('display_zone', ['eq' => $displayZone['eq']]);
                }                
            } 
            $collection->getSelect()->limit($limit);
            $collection->getSelect()->Order('banner_position','ASC');
            //$collection->setOrder('banner_position','ASC');
            //echo $collection->getSelect();exit;           
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);            
            foreach ($collection->getData() as $key => $value) {  
                $bannerType = ($value['banner_type']==1)?'category':'product';
                $mobileImage = ($value['mobile_image']!='')?$mediaUrl.$value['mobile_image']:$value['mobile_image'];                      
                $desktopImage = ($value['desktop_image']!='')?$mediaUrl.$value['desktop_image']:$value['desktop_image'];   
                $mobileImageCustom = ($value['mobile_image_custom']!='')?$value['mobile_image_custom']:$value['mobile_image_custom'];                      
                $desktopImageCustom = ($value['desktop_image_custom']!='')?$value['desktop_image_custom']:$value['desktop_image_custom'];                                         
                $homeBannersData[] = [
                    'banner_id' => $value['banner_id'],
                    'title' => $value['title'],
                    'banner_type' => $bannerType,
                    'banner_cat_ids' => $value['cat_ids'],
                    'mobile_image' => $mobileImage,
                    'desktop_image' => $desktopImage, 
                    'mobile_image_custom' => $mobileImageCustom,
                    'desktop_image_custom' => $desktopImageCustom                           
                ];
            }
            $totalCount = count($homeBannersData);
            $data = [
                    "total_count"=>$totalCount,            
                    "items"=> $homeBannersData
                ];
        } catch (NoSuchEntityException $e) {
             $data = [
                "total_count"=>0,
                "items"=> $homeBannersData
            ];            
        }
        return $data;
    }
}
