<?php
error_reporting(0);
ini_set('display_errors', 'Off');
ini_set('precision', 10);
ini_set('serialize_precision', 10);
// header("Cache-Control: no-cache, must-revalidate");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
require_once '../vendor/autoload.php';

if($_SERVER['HTTP_HOST'] == "magento.ibo.com" || $_SERVER['HTTP_HOST'] == "commerce.ibo.com") {
    //require __DIR__ . '/../var/search/staging/es.php';
    $eshost = '10.170.0.11';
    $categoryEsIndex = 'magento2_category';
    $productEsIndex = 'magento2_product_1';
    $attributeEsIndex = 'magento2_attribute';
} elseif($_SERVER['HTTP_HOST'] == "magento-staging.ibo.com" || $_SERVER['HTTP_HOST'] == "commerce-staging.ibo.com") {
   //require __DIR__ . '/../var/search/staging/es.php';
   $eshost = '10.170.0.13';
   $categoryEsIndex = 'magento2_stg_category';
   $productEsIndex = 'magento2_stg_product_1';
   $attributeEsIndex = 'magento2_stg_attribute';
} else {
   //require __DIR__ . '/../var/search/es.php';
   $eshost = '127.0.0.1';
   $categoryEsIndex = 'magento2_category';
   $productEsIndex = 'magento2_product_1';
   $attributeEsIndex = 'magento2_attribute';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($_GET) >0) {
    $returnResponse = array();
    $requestBody = $_GET; 
    $priceRegionSwitch = true;
    if(isset($requestBody['reqId']) && $requestBody['reqId']!='')
    {   
        $requestId = '';        
        addLog('Request ID '.$requestBody['reqId']." ==>".'Request Start Time '.date('Y-m-d H:i:s.U'));
        $requestId = $requestBody['reqId'];
    } 
    if($priceRegionSwitch) { 
        $region = (isset($requestBody['price_zone']))?strtolower($requestBody['price_zone']):"default"; 
        $defaultCustomerGroupID = "10_".$region;//NEED TO CHANGE DYNAMIC DEFAULT CUSTOMER GROUP FROM CONFIG
        $customerGroupId = (isset($requestBody['customer_group_id']))?$requestBody['customer_group_id']."_".$region."_1":"";
    } else { 
        $defaultCustomerGroupID = "10";//NEED TO CHANGE DYNAMIC DEFAULT CUSTOMER GROUP FROM CONFIG
        $customerGroupId = (isset($requestBody['customer_group_id']))?$requestBody['customer_group_id']."_1":""; 
        if(isset($requestBody['price_zone'])) {
            unset($requestBody['price_zone']);
        }
    }
    $default_price_key = "price_".$defaultCustomerGroupID."_1"; //Pass based on default group 
    $customer_price_key = ($customerGroupId != "")?"price_".$customerGroupId:$default_price_key; //Pass based on customer group
    $price_key = ($customerGroupId != "")?$customer_price_key:$default_price_key;    
    $sort = (isset($requestBody['sort']))?explode("|", $requestBody['sort']):""; 
    $sortKeyOld = (isset($sort) && isset($sort[0]))?$sort[0]:"sort_order"; //default sort by sort_order to used in below 
    $sortKey = (isset($sort) && isset($sort[0]))?$sort[0]:"sort_order"; //default sort by sort_order 
    $sortOrder = (isset($sort) && isset($sort[1]))?$sort[1]:"asc"; 
    $currentPageOld = (isset($requestBody['page']))? $requestBody['page']:1; //for use the original request in below
    $currentPage = (isset($requestBody['page']) && $requestBody['page'] >1 )? $requestBody['page'] - 1:0; //Doing substract as in ES doc start with 0
    $pageSize = (isset($requestBody['page_size']))?$requestBody['page_size']:20;
    $search = (isset($requestBody['search_keyword']))?$requestBody['search_keyword']:"";
    $requestBody['is_published'] = (isset($requestBody['is_published']))?$requestBody['is_published']:1;
    $categoryIds = (isset($requestBody['category_id']))?$requestBody['category_id']:"";
    $categoryCode = (!isset($requestBody['category_id']) && isset($requestBody['category_code']))?$requestBody['category_code']:"";
    $groupId = (isset($requestBody['customer_group_id'])) ? $requestBody['customer_group_id'] : $defaultCustomerGroupID;
    $serviceCategorypro = (isset($requestBody['service_category']))?$requestBody['service_category']:"";
    $allowChannelpro = (isset($requestBody['allow_channel']))?$requestBody['allow_channel']:"";
    $displayMode = (isset($requestBody['display_mode']))?strtoupper($requestBody['display_mode']):"";
    $includeOutofStock = (isset($requestBody['include_oos']))?$requestBody['include_oos']:0;
    $filterOnZone = isset($requestBody['filter_on_availability_zones']) ? $requestBody['filter_on_availability_zones'] : false;
    $esDebugger = isset($requestBody['es_debugger']) ? $requestBody['es_debugger'] : false;
    $availabilityZones = (isset($requestBody['availability_zones']))?$requestBody['availability_zones']:null;
    // print_r($requestBody);die;
    if($sortKey == "price") { 
        $sortKey = $price_key;
    }else if($sortKey == "sort_order") { 
        $sortKey = "sort_order_new";
    }else{ 
        $sortKey = $sortKey;
    }

    $filters = (isset($requestBody))?$requestBody:"";
    //Unset known keys which is not filter like sort, page etc.
    $remove = ['customer_group_id', 'sort','page', 'page_size','price_zone','display_mode','include_oos','filter_on_availability_zones','availability_zones','es_debugger','reqId']; 
    $filters = array_diff_key($filters, array_flip($remove));

    $requestData = array();
    //LIMIT & PAGINATION
    $currentPage = ($currentPage > 0)?($pageSize*$currentPage):$currentPage;
    $requestData['from'] = $currentPage;
    $requestData['size'] = $pageSize;
    //SORTING 
    // if($sortKey != "sort_order.keyword") { 
        $requestData['sort'][][$sortKey]['order'] = $sortOrder; 
        // $requestData['sort'][]['_id']['order'] = "desc";
    // }
    //DATA FIELDS
    $requestData['stored_fields'][] = "_id";
    $requestData['stored_fields'][] = "_score";
    $requestData['stored_fields'][] = "_source";
    // $requestData['fields'] = array("name","esin","brand_Id", "sku", "mrp", "per_unit_price_applicable", "per_unit_price_divisor", "per_unit_price_unit", "coverage", "url_key", "stock_status","special_price","ebo_price_tiers","ebo_price_range","image_url","tax_class_id","tax_class_id_value","is_published","is_published_value"); 
    // $requestData['fields'][] = "esin"; 
    //NEED TO FETCH Dynamic config 
    $elasticaClient = new \Elastica\Client(array( 
        'host' => $eshost, 
        'port' => 9200 
    )); 
    // echo $categoryIds;die;
    if($categoryCode) {
        $categoryCode = (strpos($categoryCode,"|"))?explode("|",$categoryCode):$categoryCode; 
        if(isset($categoryCode) && is_array($categoryCode)) { 
            $catRequest1['query']['bool']['must'][]['terms']['ibo_category_id.keyword'] = $categoryCode; 
        }else{
            $catRequest1['query']['bool']['must'][]['term']['ibo_category_id.keyword'] = $categoryCode; 
        } 
        $catRequest1['from'] = 0;
        $catRequest1['size'] = 10000;
        //Fetch category id based on ibo_code 
        $catindex = $elasticaClient->getIndex($categoryEsIndex); 
        $catpath = $catindex->getName() . '/_search'; 
        $catResponse1 = $elasticaClient->request($catpath, 'POST', json_encode($catRequest1));
        $catResponse1 = $catResponse1->getData();  
        if(isset($catResponse1)) { 
            $categoryIdsArr = array();
            foreach($catResponse1['hits']['hits'] as $categoryData) { 
                $source = $categoryData['_source'];
                $source['display_mode'] = ($displayMode)?$displayMode:$source['display_mode'];
                $returnResponse["data"]['display_mode'] = $source['display_mode'];
                $returnResponse["data"]['cms_identifier'] = ($source['cms_identifier']) ? $source['cms_identifier'] : '';
                if(isset($source['display_mode']) && $source['display_mode'] !== 'PRODUCTS' && !empty($source['content'])){                     
                    $widgetReturnReponse = $wcresult = $widgetCustomerGroupIds = [];
                    $widgetContent = json_decode($source['content'],true);
                    $categoryWT = ['CATEGORY_SCROLL','CATEGORY_LIST_CIRCLE','CATEGORY_LIST_SQUARE'];
                    $productWT  = ['PRODUCT_STACK_VERTICAL','PRODUCT_STACK_HORIZONTAL'];
                    foreach ($widgetContent as $widgetKey => $widgetValue) {
                        $widgetCustomerGroupIds = (isset($widgetValue['widget_customer_group'])) ? 
                        explode(',',$widgetValue['widget_customer_group']):[];
                        unset($widgetValue['widget_customer_group']);
                        if(in_array($widgetValue['widget_type'],$productWT) && in_array($groupId, $widgetCustomerGroupIds))
                        {
                            if(isset($widgetValue['filters']) && !empty($widgetValue['filters'])){
                                $wRequestData = getWcRequestData($widgetValue['filters'],$widgetValue['products_count'],$price_key,$serviceCategorypro,$allowChannelpro,$filterOnZone,$availabilityZones);
                                $wcindex = $elasticaClient->getIndex($productEsIndex); 
                                $wcpath = $wcindex->getName() . '/_search';
                                try{
                                    //print_r($wRequestData);
                                    $wcResponse = $elasticaClient->request($wcpath, 'POST', $wRequestData); 
                                    $wpResponseArray = $wcResponse->getData(); 
                                    //print_r($wpResponseArray);exit;
                                }catch(Exception $e){
                                    die($e->getMessage());
                                }
                            }                            
                            if(isset($wpResponseArray)) {
                              $wcresult = getWidgetProducts($wpResponseArray,$widgetValue,$default_price_key,$price_key);
                              if(!empty($wcresult)){                                
                                $widgetReturnReponse[] = $wcresult;
                              }
                            }                                                                              
                        }elseif(in_array($widgetValue['widget_type'],$categoryWT) ){
                             $widgetReturnReponse[] = $widgetValue;                             
                        }elseif(!in_array($widgetValue['widget_type'],$productWT) && !in_array($widgetValue['widget_type'],$categoryWT)){
                            if (in_array($groupId, $widgetCustomerGroupIds)) {
                                unset($widgetValue['widget_customer_group']);
                                $widgetReturnReponse[] = $widgetValue;                                
                            }else{
                                unset($widgetContent[$widgetKey]);
                            }
                        }else{
                           unset($widgetContent[$widgetKey]);
                        }
                    }
                    if(!empty($widgetReturnReponse)){
                        $returnResponse["data"]["cms_block"] = $widgetReturnReponse;                    
                    }
                } 
                if(isset($source['entity_id'])) { 
                    $categoryIdsArr[] = $source['entity_id']; 
                }
            }
            if(isset($source['display_mode']) && (string)$source['display_mode'] == 'PAGE'){
                echo json_encode($returnResponse); return;
            } 
            $categoryIds = (count($categoryIdsArr)>0)?implode("|", $categoryIdsArr):""; 
            if($categoryIds) {
                $filters['category_id'] = $categoryIds;
            }
        }
    } 
    //QUERY 
    $cntr = 0; 
    //For Search 
    if($search) { 
        //Get the list of attributes which is searchable
        if($sortKey == "sort_order_new") { 
            unset($requestData['sort']); //unset sort as for search score will be used as sort first then sort_order
            $requestData['sort'][]['_score']['order'] = "DESC"; 
            $requestData['sort'][][$sortKey]['order'] = $sortOrder; 

        }
        $catRequest['query']['bool']['must'][]['term']['is_searchable'] = "1";   
        $catRequest['from'] = 0;
        $catRequest['size'] = 10000;  
        // $catRequest['sort'][]['search_weight']['order'] = "desc";
 
        $indexAttr = $elasticaClient->getIndex($attributeEsIndex); 
        $pathAttr = $indexAttr->getName() . '/_search'; 
        // echo json_encode($catRequest);die;
        $attrResponse = $elasticaClient->request($pathAttr, 'POST', json_encode($catRequest));
        $attrResponse = $attrResponse->getData();  
        // print_r($attrResponse);die;
        if(isset($attrResponse)) {
            $requestData['query']['bool']['minimum_should_match'] = 1;
            $serchCntr = 0;
            $minimum_should_match = (str_word_count($search) > 2)?2:1;
            foreach($attrResponse['hits']['hits'] as $attrData) { 
                $source = $attrData['_source']; 
                $valueArr = array('select','boolean');
                $searchAttrCode = (in_array($source['frontend_input'],$valueArr))?$source['attribute_code']."_value":$source['attribute_code'];
                if($searchAttrCode) { 
                    $requestData['query']['bool']['should'][$serchCntr]['match'][$searchAttrCode]['query'] = $search; 
                    $requestData['query']['bool']['should'][$serchCntr]['match'][$searchAttrCode]['boost'] = $source['search_weight']+1; 
                    $requestData['query']['bool']['should'][$serchCntr]['match'][$searchAttrCode]['minimum_should_match'] = $minimum_should_match; 
                    $serchCntr++;
                }
            } 
            $requestData['query']['bool']['should'][$serchCntr]['match']['_search']['query'] = $search; 
            $requestData['query']['bool']['should'][$serchCntr]['match']['_search']['boost'] = 2; 
            $requestData['query']['bool']['should'][$serchCntr]['match']['_search']['minimum_should_match'] = $minimum_should_match;             

        } 
    } 
    /** added filter for include out of stock product -- starts here **/
    if (!isset($requestBody['ibo_stock_status'])) {
        if ($includeOutofStock == 1) {
            $filters['ibo_stock_status'] = [1,0];
        } else {
            $filters['ibo_stock_status'] = [1];
        }
    }
    /** added filter for include out of stock product -- ends here **/
    $fltrCntr = 0;
    /** unset service category from filters if filter on zone is true so that 
    it can be modified to match availability_zone filter requirement **/
    if($filterOnZone && !is_null($availabilityZones)) {
        unset($filters['service_category']);
        // $availValues = (strpos($availabilityZones,"|"))?explode("|",$availabilityZones):$availabilityZones;
        $availValues = (strpos($availabilityZones,"|"))?str_replace("|"," ",$availabilityZones):$availabilityZones;
            // $key = ($key == "availability_zones")?"ibo_availability_zone":$key;
        if ($serviceCategorypro != ''  && isset($availValues)) {
            $serviceValues = (strpos($serviceCategorypro,"|"))?explode("|",$serviceCategorypro):$serviceCategorypro;
            $nationalFlag = false;
            $finalValues = [];
            $localExists = strpos($serviceCategorypro, 'LOCAL') !== false ? true:false;
            $regionalExists = strpos($serviceCategorypro, 'REGIONAL') !== false ? true:false;
            $nationalExists = strpos($serviceCategorypro, 'NATIONAL')!== false ? true:false;
            
            if(isset($serviceValues) && is_array($serviceValues)) {
                foreach ($serviceValues as $serviceKey => $value) {
                    if (strtolower($value) !== 'national') {
                        $finalValues[] = $value;
                    }                    
                }
            } else {
                if(strtolower($serviceValues) !== 'national') {
                    $finalValues[] = $serviceValues;
                }
            }                    

            if(!$localExists && !$regionalExists && $nationalExists) {
                $requestData['query']['bool']['must'][$fltrCntr]['term']['service_category'] = 'NATIONAL'; 
                $fltrCntr++; 
            } 

            if (!empty($finalValues)) {
                if(($localExists || $regionalExists) && $nationalExists) {
                    $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][0]['bool']['must']['term']['service_category'] = 'NATIONAL';
                    if(sizeof($finalValues) > 1) {                                
                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][]['terms'] = ['service_category' => $finalValues];
                    } else {
                        if(is_array($finalValues)){
                            $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][0]['term'] = ['service_category' => $finalValues[0]];
                        }else{

                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][0]['term'] = ['service_category' => $finalValues];
                        }
                    }
                    $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][1]['multi_match']['query'] = $availValues;
                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][1]['multi_match']['fields'][] = 'ibo_availability_zone';
                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][1]['multi_match']['operator'] = 'or';
                    /*if(is_array($availValues) && sizeof($availValues) >1) {
                     //print_r($finalValues);die("269");

                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][2]['bool']['must']['terms']['ibo_availability_zone'] = $availValues; 
                    } else {

                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][2]['bool']['must']['term']['ibo_availability_zone'] = $availValues;
                       // print_r($finalValues);exit;
                    }*/

                    /*$requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][2]['bool']['must']['match']['ibo_availability_zone'] = $availValues;*/
                    $fltrCntr++;
                }

                if(($localExists || $regionalExists) && !$nationalExists) {
                    //print_r($finalValues);exit;
                    if(sizeof($finalValues) > 1) {
                        $requestData['query']['bool']['must'][$fltrCntr]['terms']['service_category'] = $finalValues;
                    } else {
                        if(is_array($finalValues)){
                             $requestData['query']['bool']['must'][$fltrCntr]['term']['service_category'] = $finalValues[0];
                        } else {
                            $requestData['query']['bool']['must'][$fltrCntr]['term']['service_category'] = $finalValues;
                        }
                    }
                    $fltrCntr++;
                   /* if(is_array($availValues) && sizeof($availValues) >1) {
                        $requestData['query']['bool']['must'][$fltrCntr]['terms']['availability_zone_value'] = $availValues; 
                    } else {
                        $requestData['query']['bool']['must'][$fltrCntr]['term']['availability_zone_value'] = $availValues;
                    }*/
                    $requestData['query']['bool']['must'][$fltrCntr]['match']['ibo_availability_zone'] = $availValues; 
                    $fltrCntr++;
                }
            }
        }
    }
    if(isset($filters)) { 
        foreach($filters as $key=>$val) { 
            if($key == "search_keyword" || $key == "category_code") { 
                continue; 
            } 
            if($key == "price"){ 
                if(strpos($val,"|")) { 
                    $pricesVales = explode("|",$val); 
                    $valCnt = 0;
                    foreach($pricesVales as $prices) { 
                        $val = explode("_",$prices); 
                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][$valCnt]['range'][$price_key]['gte'] = $val[0]; 
                        $requestData['query']['bool']['must'][$fltrCntr]['bool']['should'][$valCnt]['range'][$price_key]['lte'] = $val[1]; 
                        $valCnt++;
                    }
                }else{ 
                    $val = explode("_",$val); 
                    $requestData['query']['bool']['must'][$fltrCntr]['range'][$price_key]['gte'] = $val[0]; 
                    if($val[1] != "*" && $val[1] >0) { 
                        $requestData['query']['bool']['must'][$fltrCntr]['range'][$price_key]['lte'] = $val[1]; 
                    } 
                }
                $fltrCntr++;
            } else { 
                $key = ($key == "category_id")?"category_ids":$key; 
                $key = ($key == "allow_channel")?"allowed_channels":$key;
                $val = (strpos($val,"|"))?explode("|",$val):$val; 
                if(isset($val) && is_array($val)) { 
                    $requestData['query']['bool']['must'][$fltrCntr]['terms'][$key] = $val; 
                    $fltrCntr++; 
                } else { 
                    $requestData['query']['bool']['must'][$fltrCntr]['term'][$key] = $val; 
                    $fltrCntr++; 
                } 
            } 
        } 
    }
    $requestData['query']['bool']['must'][$fltrCntr]['term']["product_type_id"] = "simple"; //As we need to fetch only simple skus 
    //COLAPSE BY PARENT 
    $requestData['collapse']['field']="unique_group_id.keyword"; 
    
    //AGGREGATIONS 
    //Fixed aggregation for all pages
    $requestData['aggregations']['category_ids_bucket']['terms']['field'] = "category_ids"; 
    $requestData['aggregations']['category_ids_bucket']['terms']['size'] = "500";
    $requestData['aggregations']['brand_Id_bucket']['terms']['field'] = "brand_Id"; 
    $requestData['aggregations']['brand_Id_bucket']['terms']['size'] = "500";
    $requestData['aggregations']['brand_Id_bucket']['aggs']['docs']['top_hits']['size'] = 1;
    $requestData['aggregations']['brand_Id_bucket']['aggs']['docs']['top_hits']['_source']='brand_Id_value';
    $requestData['aggregations']['parent_sku_count']['cardinality']['field'] = "unique_group_id.keyword";
    $requestData['aggregations']['variant_sku_count']['terms']['size'] = "500";
    $requestData['aggregations']['variant_sku_count']['terms']['field'] = "unique_group_id.keyword";

    $requestData['aggregations']['price_stats']['stats']['field'] = $price_key; 
    $requestData['aggregations']['price_bucket']['range']['field'] = $price_key;
    // $from = 0;
    // $to = 1000;
    // for($i=0; $i <=9; $i++) {
    //     if($from==0) { 
    //         $requestData['aggregations']['price_bucket']['range']['ranges'][$i]['to'] = 1000;  
    //         $to = 1000;
    //     }
    //     elseif($i==9){
    //         $requestData['aggregations']['price_bucket']['range']['ranges'][$i]['from'] = $from;
    //         break;
    //     }
    //     else{
    //         $requestData['aggregations']['price_bucket']['range']['ranges'][$i]['from'] = $from; 
    //         $requestData['aggregations']['price_bucket']['range']['ranges'][$i]['to'] = $to; 
    //     }
    //     $from = $to;          
    //     $to = $from+1000;
    // }
    $requestData['aggregations']['price_bucket']['range']['ranges'][0]['to'] = 999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][1]['from'] = 1000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][1]['to'] = 1999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][2]['from'] = 2000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][2]['to'] = 2999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][3]['from'] = 3000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][3]['to'] = 3999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][4]['from'] = 4000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][4]['to'] = 4999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][5]['from'] = 5000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][5]['to'] = 5999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][6]['from'] = 6000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][6]['to'] = 6999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][7]['from'] = 7000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][7]['to'] = 7999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][8]['from'] = 8000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][8]['to'] = 8999;   
    $requestData['aggregations']['price_bucket']['range']['ranges'][9]['from'] = 9000; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][9]['to'] = 9999; 
    $requestData['aggregations']['price_bucket']['range']['ranges'][10]['from'] = 10000;         
    //Dynamic aggregations based on category    
    $filterArr = array();
    // print_r($categoryIds);die;
    if(isset($search)) { 
        //Fetch filters based on search
        $filterArr = array(); 
        $attrLblCode = array(); 
        $attrLblCode['brand_Id'] = "Brand";
        //Get the list of attributes which is filterable with search
        // $searchAttrRequest['query']['bool']['must'][]['term']['is_filterable_in_search'] = "1"; 
        // $searchAttrRequest['from'] = 0;
        // $searchAttrRequest['size'] = 10000;            
        // $indexFltrAttr = $elasticaClient->getIndex($attributeEsIndex); 
        // $pathFltrAttr = $indexFltrAttr->getName() . '/_search'; 
        // $attrFltrResponse = $elasticaClient->request($pathFltrAttr, 'POST', json_encode($searchAttrRequest));
        // $attrFltrResponse = $attrFltrResponse->getData();  
        // if(isset($attrFltrResponse)) {
        //     $serchCntr = 0;
        //     foreach($attrFltrResponse['hits']['hits'] as $attrData) { 
        //         $source = $attrData['_source']; 
        //         $filterArr[] = $source['attribute_code']; 
        //         $attrLblCode[$source['attribute_code']] = $source['label']; 
        //     } 
        // }         

    }
    if($categoryIds) { 
        //Fetch filters based on category
        $filterArr = array();
        $attrLblCode = array(); 
        $categoryIds = (strpos($categoryIds,"|"))?explode("|",$categoryIds):$categoryIds; 
        if(isset($categoryIds) && is_array($categoryIds)) { 
            $catRequest['query']['bool']['must'][]['terms']['entity_id'] = $categoryIds; 
        }else{
            $catRequest['query']['bool']['must'][]['term']['entity_id'] = $categoryIds; 
        }
        $catRequest['from'] = 0;
        $catRequest['size'] = 10000;  
        //Need to fetch dynamic index 
        $catindex = $elasticaClient->getIndex($categoryEsIndex); 
        $catpath = $catindex->getName() . '/_search'; 
        $catResponse = $elasticaClient->request($catpath, 'POST', json_encode($catRequest));
        $catResponse = $catResponse->getData();  
        if(isset($catResponse) && count($catResponse['hits']['hits'])>0) {
            foreach($catResponse['hits']['hits'] as $categoryData) { 
                $source = $categoryData['_source'];
                $source['display_mode'] = ($displayMode)?$displayMode:$source['display_mode'];
                $returnResponse["data"]['display_mode'] = $source['display_mode'];
                $returnResponse["data"]['cms_identifier'] = ($source['cms_identifier']) ? $source['cms_identifier'] : '';
                if(isset($source['display_mode']) && $source['display_mode'] !== 'PRODUCTS' && !empty($source['content'])){
                    
                    $widgetReturnReponse = $wcresult = $widgetCustomerGroupIds = [];
                    $widgetContent = json_decode($source['content'],true);
                    $productWT = ['PRODUCT_STACK_VERTICAL','PRODUCT_STACK_HORIZONTAL'];
                    $categoryWT = ['CATEGORY_SCROLL','CATEGORY_LIST_CIRCLE','CATEGORY_LIST_SQUARE'];
                    foreach ($widgetContent as $widgetKey => $widgetValue) {
                        $widgetCustomerGroupIds = (isset($widgetValue['widget_customer_group'])) ? 
                        explode(',',$widgetValue['widget_customer_group']):[];
                        unset($widgetValue['widget_customer_group']);
                        if(in_array($widgetValue['widget_type'],$productWT) && in_array($groupId, $widgetCustomerGroupIds))
                        {
                            if(isset($widgetValue['filters']) && !empty($widgetValue['filters'])){
                                $wRequestData = getWcRequestData($widgetValue['filters'],$widgetValue['products_count'],$price_key,$serviceCategorypro,$allowChannelpro,$filterOnZone,$availabilityZones);
                                $wcindex = $elasticaClient->getIndex($productEsIndex); 
                                $wcpath = $wcindex->getName() . '/_search';
                                try{
                                    //print_r($wRequestData);
                                    $wcResponse = $elasticaClient->request($wcpath, 'POST', $wRequestData); 
                                    $wpResponseArray = $wcResponse->getData(); 
                                    //print_r($wpResponseArray);exit;
                                }catch(Exception $e){
                                    die($e->getMessage());
                                }
                            }  
                            if(isset($wpResponseArray)) {
                              $wcresult = getWidgetProducts($wpResponseArray,$widgetValue,$default_price_key,$price_key);
                              if(!empty($wcresult)){  
                                $widgetReturnReponse[] = $wcresult;
                              }else{
                                unset($widgetContent[$widgetKey]);
                              }
                            }             
                        }elseif(in_array($widgetValue['widget_type'],$categoryWT) ){
                             $widgetReturnReponse[] = $widgetValue;                             
                        }elseif(!in_array($widgetValue['widget_type'],$productWT) && !in_array($widgetValue['widget_type'],$categoryWT)){
                            if (in_array($groupId, $widgetCustomerGroupIds)) {
                                unset($widgetValue['widget_customer_group']);
                                $widgetReturnReponse[] = $widgetValue;                                
                            }else{
                                unset($widgetContent[$widgetKey]);
                            }
                        }else{
                           unset($widgetContent[$widgetKey]);
                        }
                    }
                    if(!empty($widgetReturnReponse)){
                        $returnResponse["data"]["cms_block"] = $widgetReturnReponse;                    
                    }
                } 
                if(isset($source['display_mode']) && (string)$source['display_mode'] == 'PAGE'){
                    if(isset($requestId) && $requestId!=''){
                        addLog('Request ID '.$requestId." ==>".'Request End Time '.date('Y-m-d H:i:s.U'));          
                    }
                    echo json_encode($returnResponse); return;
                } 
                if(isset($source['filterable_attributes']) && isset($source['filterable_attributes_label'])) { 
                    $filterCodeArr = $source['filterable_attributes']; 
                    $filterLabelArr = $source['filterable_attributes_label']; 
                    $filterArr = explode(",", $filterCodeArr);
                    $filterLabelArr = explode(",", $filterLabelArr); 
                    $attrLblCode = array_combine($filterArr,$filterLabelArr);
                }
            } 
        } 
        $attrLblCode['brand_Id'] = "Brand";
    } 
    if(is_array($filterArr) && count($filterArr) > 0) { 
        foreach($filterArr as $key => $filter) { 
            // print_r($filter);die;
            $requestData['aggregations'][$filter.'_bucket']['terms']['field'] = $filter; 
            $requestData['aggregations'][$filter.'_bucket']['terms']['size'] = "500";
            $requestData['aggregations'][$filter.'_bucket']['aggs']['docs']['top_hits']['size'] = 1;
            $requestData['aggregations'][$filter.'_bucket']['aggs']['docs']['top_hits']['_source']=$filter.'_value';
        }
    }
    if($esDebugger){
       print_r(json_encode($requestData));die;
    }
    // print_r(json_encode($requestData));die; 
    try{ 
        //Need to fetch dynamic index 
        $index = $elasticaClient->getIndex($productEsIndex); 
        $path = $index->getName() . '/_search'; 
        $response = $elasticaClient->request($path, 'POST', json_encode($requestData)); 
        $responseArray = $response->getData(); 
        // print_r($responseArray);die;
        //Aggregation foreach Start 
        if(isset($responseArray) && isset($responseArray['aggregations'])) { 
            //echo "<pre>";print_r($responseArray['aggregations']);die;
            $aggrCnt=0;
            $cat_aggr = array();
            $variantCountData = [];
            foreach($responseArray['aggregations'] as $attribute => $aggregation){
                 if ($attribute == 'variant_sku_count') {
                    foreach($aggregation["buckets"] as $childVal) { 
                            if($childVal['doc_count'] > 0 ) { 
                                $variantCountData[$childVal['key']] = $childVal['doc_count'];
                            }
                    }
                    continue;
                 }
                 //echo "<pre>";
                 //print_r($variantCountData);die;
                    $attrCode = str_replace("_bucket","",$attribute);
                    $childCntr=0;
                    if(isset($attrCode) && $attrCode == "category_ids"){
                        foreach($aggregation["buckets"] as $childVal) { 
                            if($childVal['doc_count'] > 0 ) { 
                                $cat_aggr[$childVal['key']] = $childVal['doc_count'];
                            }
                        }
                    }
                    elseif(isset($attrCode) && $attrCode == "price") { 
                        if(isset($aggregation["buckets"]) && count($aggregation["buckets"]) > 0) { 
                            $priceCnt = 0;
                            foreach($aggregation["buckets"] as $priceRange) { 
                                if(isset($priceRange['doc_count']) && $priceRange['doc_count'] > 0) {                                   
                                    $from = (isset($priceRange['from']) && $priceRange['from'] != "*")?round($priceRange['from']):"0";
                                    $maxAmnt = (isset($responseArray['aggregations']["price_stats"]['max']))?round($responseArray['aggregations']["price_stats"]['max']):0;
                                    $to = (isset($priceRange['to']) && $priceRange['to'] != "*")?round($priceRange['to']):$maxAmnt;
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$priceCnt]['label'] = $from.'-'.$to; 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$priceCnt]['value'] = $from.'_'.$to; 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$priceCnt]['count'] = $priceRange['doc_count'];
                                    if(isset($_GET[$attrCode]) && $_GET[$attrCode] == $from.'_'.$to) { 
                                        $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$priceCnt]['is_applied'] = true; 
                                    }                                        
                                    $priceCnt++;
                                }
                            }
                            if($priceCnt) { 
                                // $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][0]['count'] = $aggregation['count']; 
                                $returnResponse['data']['products']['aggregations'][$aggrCnt]['label'] = "Price";
                                $returnResponse['data']['products']['aggregations'][$aggrCnt]['attribute_code'] = $attrCode; 
                                // $keys = array_column($returnResponse['data']['products']['aggregations'][$aggrCnt]['options'], 'count'); 
                                // array_multisort($keys, SORT_DESC, $returnResponse['data']['products']['aggregations'][$aggrCnt]['options']);    
                            }        
                        } 
                        // $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][0]['value']['min'] = $aggregation['min']; 
                        // $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][0]['value']['max'] = $aggregation['max']; 

                        $aggrCnt++;
                    } else { 
                        if(isset($aggregation["buckets"]) && count($aggregation["buckets"]) > 0) { 
                            foreach($aggregation["buckets"] as $childVal) { 
                                if($childVal['doc_count'] > 0 ) { 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$childCntr]['is_applied'] = false; 
                                    if(isset($_GET[$attrCode])) { 
                                        $tempAttrVal = explode("|",$_GET[$attrCode]); 
                                        if(in_array($childVal['key'],$tempAttrVal)) { 
                                            $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$childCntr]['is_applied'] = true; 
                                        } 
                                    } 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$childCntr]['label'] = (isset($childVal['docs']) && count($childVal['docs']['hits']['hits']) > 0 && isset($childVal['docs']['hits']['hits'][0]['_source'][$attrCode.'_value']))?$childVal['docs']['hits']['hits'][0]['_source'][$attrCode.'_value']:""; 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$childCntr]['value'] = $childVal['key']; 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$childCntr]['count'] = $childVal['doc_count']; 
                                    $childCntr++;
                                }
                            } 
                            if($childCntr > 0) { 
                                $keys = array_column($returnResponse['data']['products']['aggregations'][$aggrCnt]['options'], 'count'); 
                                array_multisort($keys, SORT_DESC, $returnResponse['data']['products']['aggregations'][$aggrCnt]['options']);            
                                $returnResponse['data']['products']['aggregations'][$aggrCnt]['attribute_code'] = $attrCode; 
                                $returnResponse['data']['products']['aggregations'][$aggrCnt]['label'] = (isset($attrLblCode[$attrCode]))?$attrLblCode[$attrCode]:$attrCode; 
                                $returnResponse['data']['products']['aggregations'][$aggrCnt]['count'] = $childCntr; 
                                $aggrCnt++; 
                            } 
                        } 
                    }  
            } 
            //Aggregation foreach End
            if(count($cat_aggr) > 0) { 
                $categoryIds = array_keys($cat_aggr); 
                $catRequest = array();
                $catRequest['query']['bool']['must'][]['term']['level'] = 4; //As we are showing only level 4 category 
                if(isset($categoryIds) && is_array($categoryIds)) { 
                    $catRequest['query']['bool']['must'][]['terms']['entity_id'] = $categoryIds; 
                }else{
                    $catRequest['query']['bool']['must'][]['term']['entity_id'] = $categoryIds; 
                } 
                $catRequest['from'] = 0;
                $catRequest['size'] = 10000;
                // print_r($catRequest);die;
                //Need to fetch dynamic index 
                $index = $elasticaClient->getIndex($categoryEsIndex); 
                $path = $index->getName() . '/_search'; 
                $catResponse = $elasticaClient->request($path, 'POST', json_encode($catRequest));
                $catResponse = $catResponse->getData();  
                if(isset($catResponse)) {
                    $catchildCntr=0;
                    foreach($catResponse['hits']['hits'] as $categoryData) { 
                        $source = $categoryData['_source']; 
                        if(isset($source['category_name'])) { 
                            if(isset($_GET['category_id'])) { 
                                $tempAttrVal = explode("|",$_GET['category_id']);
                                $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$catchildCntr]['is_applied'] = false; 
                                if(in_array($source['entity_id'],$tempAttrVal)) { 
                                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$catchildCntr]['is_applied'] = true; 
                                }
                            }
                            $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$catchildCntr]['label'] = $source['category_name']; 
                            $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$catchildCntr]['value'] = $source['entity_id']; 
                            $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$catchildCntr]['category_code'] = $source['ibo_category_id']; 
                            $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$catchildCntr]['count'] = $cat_aggr[$source['entity_id']]; 
                        }
                        $catchildCntr++;
                    }
                    $keys = array_column($returnResponse['data']['products']['aggregations'][$aggrCnt]['options'], 'count'); 
                    array_multisort($keys, SORT_DESC, $returnResponse['data']['products']['aggregations'][$aggrCnt]['options']);

                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['attribute_code'] = 'category_id'; 
                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['label'] = 'Category'; 
                    $returnResponse['data']['products']['aggregations'][$aggrCnt]['count'] = $catchildCntr; 
                }                
            }
        }
        if(isset($responseArray) ) { 
            $totalCount = (isset($responseArray['aggregations']['parent_sku_count']['value']))?$responseArray['aggregations']['parent_sku_count']['value']:$responseArray['hits']['total']['value']; 
            $returnResponse['data']['products']['total_count'] = $totalCount; 
            // if(isset($_GET[$attrCode])) { 
            //     $tempAttrVal = explode("|",$_GET[$attrCode]); 
            //     if(in_array($childVal['key'],$tempAttrVal)) { 
            //         $returnResponse['data']['products']['aggregations'][$aggrCnt]['options'][$childCntr]['is_applied'] = true; 
            //     } 
            // }
            $returnResponse['data']['products']["sort_fields"]["default"] = "sort_order";
            $returnResponse['data']['products']["sort_fields"]['options'][0]['label'] = "Price Low-High";
            $returnResponse['data']['products']["sort_fields"]['options'][0]['value'] = "price";
            $returnResponse['data']['products']["sort_fields"]['options'][0]['sort_direction'] = "ASC"; 
            $returnResponse['data']['products']["sort_fields"]['options'][0]['is_applied'] = (isset($sortKeyOld) && $sortKeyOld == "price" && $sortOrder == "ASC")?true:false;
            $returnResponse['data']['products']["sort_fields"]['options'][1]['label'] = "Price High-Low";
            $returnResponse['data']['products']["sort_fields"]['options'][1]['value'] = "price";
            $returnResponse['data']['products']["sort_fields"]['options'][1]['sort_direction'] = "DESC"; 
            $returnResponse['data']['products']["sort_fields"]['options'][1]['is_applied'] = (isset($sortKeyOld) && $sortKeyOld == "price" && $sortOrder == "DESC")?true:false;
            $returnResponse['data']['products']["sort_fields"]['options'][2]['label'] = "Popularity";
            $returnResponse['data']['products']["sort_fields"]['options'][2]['value'] = "sort_order";
            $returnResponse['data']['products']["sort_fields"]['options'][2]['sort_direction'] = "ASC"; 
            $returnResponse['data']['products']["sort_fields"]['options'][2]['is_applied'] = (isset($sortKeyOld) && $sortKeyOld == "sort_order")?true:false;            
            $returnResponse['data']['products']["page_info"]['page_size'] = (int)$pageSize;
            $returnResponse['data']['products']["page_info"]['current_page'] = (int)$currentPageOld;
            $totalPages = ($totalCount > $pageSize)?ceil($totalCount/$pageSize):1;
            $returnResponse['data']['products']["page_info"]['total_pages'] = $totalPages;
            $iCnt=0; 
            //Item foreach Start 
            $returnResponse['data']['products']['items'] = array();
            foreach($responseArray['hits']['hits'] as $product) { 
                $productData = $product['_source'];
                // print_r($productData);die; 
                $productDataFinalPriceValue = $productData[$price_key];
                $returnResponse['data']['products']['items'][$iCnt]['id'] = $product['_id'];
                $returnResponse['data']['products']['items'][$iCnt]['unique_group_id'] = $productData['unique_group_id']; 
                $returnResponse['data']['products']['items'][$iCnt]['name'] = (isset($productData['meta_title']))?$productData['meta_title']:$productData['name']; 
                $returnResponse['data']['products']['items'][$iCnt]['esin'] = (isset($productData['esin']))?$productData['esin']:"";
                $returnResponse['data']['products']['items'][$iCnt]['slug'] = (isset($productData['slug']))?$productData['slug']:"";
                /** added recently upon request of ankur -- starts here **/
                /*$returnResponse['data']['products']['items'][$iCnt]['service_category'] = (isset($productData['service_category']))?$productData['service_category']:"";*/
                $returnResponse['data']['products']['items'][$iCnt]['availability_zone'] = (isset($productData['ibo_availability_zone']))?json_decode($productData['ibo_availability_zone'],true):"";
                $returnResponse['data']['products']['items'][$iCnt]['service_category'] = (isset($productData['service_category']))?$productData['service_category'][1]:"";
                $returnResponse['data']['products']['items'][$iCnt]['ibo_stock_status'] = (isset($productData['ibo_stock_status']))?(int)$productData['ibo_stock_status']:"";
                $returnResponse['data']['products']['items'][$iCnt]['category_id'] = (isset($productData['ibo_category_id']))?$productData['ibo_category_id']:"";
                $returnResponse['data']['products']['items'][$iCnt]['is_bom'] = (isset($productData['is_bom']))?$productData['is_bom']:"";
                $returnResponse['data']['products']['items'][$iCnt]['is_bom_value'] = (isset($productData['is_bom_value']))?$productData['is_bom_value']:"";
                $returnResponse['data']['products']['items'][$iCnt]['is_lot_controlled'] = (isset($productData['is_lot_controlled']))?$productData['is_lot_controlled']:"";
                $returnResponse['data']['products']['items'][$iCnt]['is_lot_controlled_value'] = (isset($productData['is_lot_controlled_value']))?$productData['is_lot_controlled_value']:"";
                $returnResponse['data']['products']['items'][$iCnt]['sale_uom'] = (isset($productData['sale_uom']))?$productData['sale_uom']:"";
                $returnResponse['data']['products']['items'][$iCnt]['sale_uom_value'] = (isset($productData['sale_uom_value']))?$productData['sale_uom_value']:"";
                $returnResponse['data']['products']['items'][$iCnt]['store_fulfilment_mode'] = (isset($productData['store_fulfilment_mode']))?$productData['store_fulfilment_mode']:"";
                $returnResponse['data']['products']['items'][$iCnt]['store_fulfilment_mode_value'] = (isset($productData['store_fulfilment_mode_value']))?$productData['store_fulfilment_mode_value']:"";
                $returnResponse['data']['products']['items'][$iCnt]['barcode'] = (isset($productData['barcode']))?$productData['barcode']:"";
                $returnResponse['data']['products']['items'][$iCnt]['ean'] = (isset($productData['ean']))?$productData['ean']:"";
                $returnResponse['data']['products']['items'][$iCnt]['package_dimension']['height_in_cm']= (isset($productData['package_height_in_cm']))?$productData['package_height_in_cm']:"";
                $returnResponse['data']['products']['items'][$iCnt]['package_dimension']['length_in_cm']= (isset($productData['package_length_in_cm']))?$productData['package_length_in_cm']:"";
                $returnResponse['data']['products']['items'][$iCnt]['package_dimension']['width_in_cm']= (isset($productData['package_width_in_cm']))?$productData['package_width_in_cm']:"";
                $returnResponse['data']['products']['items'][$iCnt]['package_dimension']['weight_in_kg']= (isset($productData['package_weight_in_kg']))?$productData['package_weight_in_kg']:"";
                /** added recently upon request of ankur -- ends here **/
                $returnResponse['data']['products']['items'][$iCnt]['brand_Id'] = (isset($productData['brand_Id']))?(string)$productData['brand_Id']:""; 
                $returnResponse['data']['products']['items'][$iCnt]['brand_Id_value'] = (isset($productData['brand_Id_value']))?(string)$productData['brand_Id_value']:""; 
                $returnResponse['data']['products']['items'][$iCnt]['department'] = (isset($productData['department']))?$productData['department']:""; 
                $returnResponse['data']['products']['items'][$iCnt]['class'] = (isset($productData['class']))?$productData['class']:"";
                $returnResponse['data']['products']['items'][$iCnt]['sort_order'] = (isset($productData['sort_order']))?$productData['sort_order']:"";
                $returnResponse['data']['products']['items'][$iCnt]['subclass'] = (isset($productData['subclass']))?$productData['subclass']:"";
                $returnResponse['data']['products']['items'][$iCnt]['sku'] = (isset($productData['sku']))?$productData['sku']:"";
                $returnResponse['data']['products']['items'][$iCnt]['mrp'] = (isset($productData['mrp']))?$productData['mrp']:"";
                $returnResponse['data']['products']['items'][$iCnt]['per_unit_price_applicable'] = (isset($productData["per_unit_price_applicable"]))?$productData["per_unit_price_applicable"]:NULL;//"PER_UNIT_PRICE_APPLICABLE"; 
                $returnResponse['data']['products']['items'][$iCnt]['per_unit_price_divisor'] = (isset($productData["per_unit_price_divisor"]))?$productData["per_unit_price_divisor"]:NULL;//"PER_UNIT_PRICE_DIVISOR"; 
                $returnResponse['data']['products']['items'][$iCnt]['per_unit_price_unit'] = (isset($productData["per_unit_price_unit"]))?$productData["per_unit_price_unit"]:NULL;//"PER_UNIT_PRICE_UNIT"; 
                if(isset($productData["per_unit_price_divisor"])) { 
                    $returnResponse['data']['products']['items'][$iCnt][$productData["per_unit_price_divisor"]] =  (isset($productData[$productData["per_unit_price_divisor"]]))?$productData[$productData["per_unit_price_divisor"]]:"";//"COVERAGE"; 
                }
                $returnResponse['data']['products']['items'][$iCnt]['url_key'] = $productData['url_key']; 
                $returnResponse['data']['products']['items'][$iCnt]['is_published'] = $productData['is_published']; 
                /*$returnResponse['data']['products']['items'][$iCnt]['allowed_channels'] = $productData['allowed_channels']; 
                $returnResponse['data']['products']['items'][$iCnt]['courier_flag'] = (isset($productData['courier_flag']))?$productData['courier_flag']:"";*/ 
                
                $returnResponse['data']['products']['items'][$iCnt]['allowed_channels'] = $productData['allowed_channels'][1]; 
                $returnResponse['data']['products']['items'][$iCnt]['courier_type'] = (isset($productData['ibo_courier_type']))?(string)$productData['ibo_courier_type']:""; 
                
                $returnResponse['data']['products']['items'][$iCnt]['no_of_variants'] = isset($variantCountData[$productData['unique_group_id']]) ? $variantCountData[$productData['unique_group_id']]:1;//$productData["variant_count"];//"NO_OF_VARIANTS"; 
                $taxRate = (isset($productData['tax_class_id_value']))?(int)$productData['tax_class_id_value']:0;
                //PRICE_RANGE as per defaut customer group qty 1 price
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['final_price']['value'] = (float)number_format((float)($productData[$default_price_key]/1), 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $productData[$default_price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                } 
                $productData['mrp'] = (isset($productData['mrp']) && $productData['mrp'] > 0)?(int)$productData['mrp']:0;
                $productData_default_price_key = (int)$productData[$default_price_key];
                $difference = $productData['mrp'] - $productData_default_price_key;

                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['discount']['percent_off'] = $percOff;        
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $productData[$default_price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                } 
                $ebo_price_range_without_tax = $productData[$default_price_key] * 100 / (100 + $taxRate); 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['final_price']['value'] = (float)number_format((float)$ebo_price_range_without_tax, 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                $difference = $productData['mrp'] - $ebo_price_range_without_tax; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }            
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['discount']['percent_off'] = $percOff;             
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $ebo_price_range_without_tax;
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['unit_price'] = $finalUnitPrice;
                }
            
                //PRice Tier based on customer group price/null if not there 
                $tierPrice_without_tax = $productData[$price_key] * 100 / (100 + $taxRate); 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['final_price']['value'] = (float)number_format((float)($productDataFinalPriceValue/1), 2, '.', '');//"CUSTOMER_GROUP_PRICE";
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                $difference = $productData['mrp'] - $productData[$price_key]; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                } 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['discount']['percent_off'] = $percOff; 
                if(isset($productData[$price_key]) && isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) {  
                    $finalPrice = $productData[$price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                }     
            
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['final_price']['value'] = (float)number_format((float)$tierPrice_without_tax, 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['final_price']['currency'] = "INR";
                $difference = $productData['mrp'] - $tierPrice_without_tax; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }             
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['discount']['percent_off'] = $percOff;             
                if(isset($productData[$price_key]) && isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"])  && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalUnitPrice = ceil($tierPrice_without_tax / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['unit_price'] = $finalUnitPrice;
                }
                // $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax'] = ($customerGroupId != "")?$productData[$customer_price_key]/1:NULL;//"CUSTOMER_GROUP_PRICE"; 
                // $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax'] = ($customerGroupId != "")?round($tierPrice_without_tax,2):NULL;  
                /** added recently upon request of ankur -- starts here **/
                if (!empty($productData['ibo_tier_price'])) {
                    
                    $tierPrices = json_decode($productData['ibo_tier_price'],true);
                    $tierTempData = [];
                    $tierDefaultCustomerGroupID = "10";//NEED TO CHANGE DYNAMIC DEFAULT CUSTOMER GROUP FROM CONFIG
                    $tierRegion = (isset($requestBody['price_zone']))?strtolower($requestBody['price_zone']):"default"; 
                    $tierCustomerGroupId = (isset($requestBody['customer_group_id']))?$requestBody['customer_group_id']: $tierDefaultCustomerGroupID;
                    foreach ($tierPrices as $key => $tierPrice) {
                        if ($tierPrice['zone'] == $tierRegion && $tierPrice['cust_group_id'] == $tierCustomerGroupId) {
                            $tierTempData[] = $tierPrice;
                        }
                        if ($tierPrice['zone'] == 'default' && $tierPrice['cust_group_id'] == $tierCustomerGroupId) {
                            $tierDefaultData[] = $tierPrice;
                        }
                    }
                    if (empty($tierTempData)) {
                        $tierTempData = $tierDefaultData;
                    }

                    $tierData = [];
                    
                    if (!empty($tierTempData)) {
                        $tierCounter = 0;
                        $lastTier = array_key_last($tierTempData);
                        foreach ($tierTempData as $key => $tiers) {
                            if ($key == $lastTier) {
                                $maxQty = null;
                            } else {
                                $maxQty = $tierTempData[$tierCounter+1]['qty'] - 1;
                            }
                            $tierData[$tierCounter]['cust_group_id'] = $tiers['cust_group_id'];
                            $tierData[$tierCounter]['zone'] = $tiers['zone'];
                            $tierData[$tierCounter]['min_qty'] = $tiers['qty'];
                            $tierData[$tierCounter]['max_qty'] = $maxQty;
                            $tierData[$tierCounter]['price'] = (float)number_format((float)$tiers['price'], 2, '.', '');
                            $tierCounter ++;
                        }
                    }

                    $returnResponse['data']['products']['items'][$iCnt]['tier_price'] = $tierData;
                } else {
                    $returnResponse['data']['products']['items'][$iCnt]['tier_price'] = [];
                }
                /** added recently upon request of ankur -- ends here **/
                $returnResponse['data']['products']['items'][$iCnt]['image_custom']['url'] = (isset($productData['base_image_custom'])) ? $productData['base_image_custom'] : '';
                $returnResponse['data']['products']['items'][$iCnt]['image']['url'] = $productData['image_url'];
                $returnResponse['data']['products']['items'][$iCnt]['image']['position'] = 0;
                $iCnt++;
        } 
        //Item foreach End
    } 
        if(isset($requestId) && $requestId!=''){          
            addLog('Request ID '.$requestId." ==>".'Request End Time '.date('Y-m-d H:i:s.U'));           
        }
        echo json_encode($returnResponse); 
    }catch(Exception $e){ 
        http_response_code(500); 
        print_r($e->getMessage()); die;
    }
}
else{
    http_response_code(400);
    // throw new Exception("Bad Request.");
    die;
}
function getWidgetProducts($wpResponseArray, $wc, $default_price_key, $price_key){
        
        if(isset($wpResponseArray) && $wpResponseArray['hits']['total']['value'] > 0) {
            $returnResponse['widget_type'] = (isset($wc['widget_type'])) ? $wc['widget_type'] :'PRODUCTS';
            $returnResponse['widget_title'] = (isset($wc['widget_title'])) ? $wc['widget_title'] :'';
            $returnResponse['widget_app_link'] = (isset($wc['widget_app_link'])) ? $wc['widget_app_link'] :'';
            $returnResponse['widget_web_link'] = (isset($wc['widget_web_link'])) ? $wc['widget_web_link'] :'';
            $returnResponse['widget_web_link'] = (isset($wc['widget_web_link'])) ? $wc['widget_web_link'] :'';
            $iCnt=0; 
            //Item foreach Start 
            $returnResponse['widget_meta_data'] = array();
            foreach($wpResponseArray['hits']['hits'] as $product) { 
                $productData = $product['_source'];
                // print_r($productData);die;
                $returnResponse['widget_meta_data'][$iCnt]['id'] = $product['_id'];
                $returnResponse['widget_meta_data'][$iCnt]['unique_group_id'] = $productData['unique_group_id']; 
                $returnResponse['widget_meta_data'][$iCnt]['name'] = (isset($productData['meta_title']))?$productData['meta_title']:$productData['name']; 
                $returnResponse['widget_meta_data'][$iCnt]['esin'] = $productData['esin'];
                $returnResponse['widget_meta_data'][$iCnt]['slug'] = (isset($productData['slug']))?$productData['slug']:"";
                $returnResponse['widget_meta_data'][$iCnt]['brand_Id'] = (isset($productData['brand_Id']))?(string)$productData['brand_Id']:""; 
                $returnResponse['widget_meta_data'][$iCnt]['sku'] = $productData['sku'];
                $returnResponse['widget_meta_data'][$iCnt]['mrp'] = (isset($productData['mrp']))?$productData['mrp']:"";
                $returnResponse['widget_meta_data'][$iCnt]['per_unit_price_applicable'] = (isset($productData["per_unit_price_applicable"]))?$productData["per_unit_price_applicable"]:NULL;//"PER_UNIT_PRICE_APPLICABLE"; 
                $returnResponse['widget_meta_data'][$iCnt]['per_unit_price_divisor'] = (isset($productData["per_unit_price_divisor"]))?$productData["per_unit_price_divisor"]:NULL;//"PER_UNIT_PRICE_DIVISOR"; 
                $returnResponse['widget_meta_data'][$iCnt]['per_unit_price_unit'] = (isset($productData["per_unit_price_unit"]))?$productData["per_unit_price_unit"]:NULL;//"PER_UNIT_PRICE_UNIT"; 
                if(isset($productData["per_unit_price_divisor"])) { 
                    $returnResponse['widget_meta_data'][$iCnt][$productData["per_unit_price_divisor"]] =  (isset($productData[$productData["per_unit_price_divisor"]]))?$productData[$productData["per_unit_price_divisor"]]:"";//"COVERAGE"; 
                }
                $returnResponse['widget_meta_data'][$iCnt]['url_key'] = $productData['url_key']; 
                $returnResponse['widget_meta_data'][$iCnt]['is_published'] = $productData['is_published']; 
                $returnResponse['widget_meta_data'][$iCnt]['allowed_channels'] = $productData['allowed_channels'][1]; 
                $returnResponse['widget_meta_data'][$iCnt]['courier_flag'] = (isset($productData['courier_flag']))?$productData['courier_flag']:""; 
                $returnResponse['widget_meta_data'][$iCnt]['no_of_variants'] = isset($variantCountData[$productData['unique_group_id']]) ? $variantCountData[$productData['unique_group_id']]:1;//$productData["variant_count"];//"NO_OF_VARIANTS"; 
                $taxRate = (isset($productData['tax_class_id_value']))?(int)$productData['tax_class_id_value']:0;
                //PRICE_RANGE as per defaut customer group qty 1 price
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['final_price']['value'] = $productData[$default_price_key]/1; 
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $productData[$default_price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                } 
                $productData['mrp'] = (isset($productData['mrp']) && $productData['mrp'] > 0)?(int)$productData['mrp']:0;
                $productDataDefault_price_key = (int)$productData[$default_price_key];
                $difference = $productData['mrp'] - $productDataDefault_price_key;

                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['discount']['percent_off'] = $percOff;        
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $productData[$default_price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                } 
                $ebo_price_range_without_tax = $productData[$default_price_key] * 100 / (100 + $taxRate); 
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['final_price']['value'] = round($ebo_price_range_without_tax,2); 
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                $difference = $productData['mrp'] - $ebo_price_range_without_tax; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }            
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['discount']['percent_off'] = $percOff;             
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $ebo_price_range_without_tax;
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['widget_meta_data'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['unit_price'] = $finalUnitPrice;
                }
            
                //PRice Tier based on customer group price/null if not there 
                $tierPrice_without_tax = $productData[$price_key] * 100 / (100 + $taxRate); 
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['final_price']['value'] = $productData[$price_key]/1;//"CUSTOMER_GROUP_PRICE";  
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                $difference = $productData['mrp'] - $productData[$price_key]; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                } 
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['discount']['percent_off'] = $percOff; 
                if(isset($productData[$price_key]) && isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) {  
                    $finalPrice = $productData[$price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                }     
            
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['final_price']['value'] = round($tierPrice_without_tax,2);  
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['final_price']['currency'] = "INR";
                $difference = $productData['mrp'] - $tierPrice_without_tax; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }             
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['discount']['amount_off'] = (float)number_format((float)$amountOff, 2, '.', '');
                $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['discount']['percent_off'] = $percOff;             
                if(isset($productData[$price_key]) && isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"])  && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalUnitPrice = ceil($tierPrice_without_tax / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['widget_meta_data'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['unit_price'] = $finalUnitPrice;
                }
                $returnResponse['widget_meta_data'][$iCnt]['image_custom']['url'] = (isset($productData['base_image_custom'])) ? $productData['base_image_custom'] : '';
                $returnResponse['widget_meta_data'][$iCnt]['image']['url'] = $productData['image_url'];
                $returnResponse['widget_meta_data'][$iCnt]['image']['position'] = 0;
                
                $iCnt++;
            }
        }
        $returnResponse = ($returnResponse) ? $returnResponse :[];
        return $returnResponse; 
    }

function getWcRequestData($filters,$productCount,$price_key,$serviceCategorypro,$allowChannelpro,$filterOnZone,$availabilityZones)
{
    //print_r($filters);
    $wcRequestData = [];    
    //LIMIT & PAGINATION
    $wcRequestData['from'] = 0;
    $wcRequestData['size'] = $productCount;
    //SORTING 
    $sortKey = isset($filters['sort']) ? $filters['sort'] : "sort_order";
    $sortOrder = isset($filters['sort_order']) ? $filters['sort_order'] : "ASC";
    if($sortKey == "price") { 
        $sortKey = $price_key;
    }else if($sortKey == "sort_order") { 
        $sortKey = "sort_order_new";
    }else{ 
        $sortKey = $sortKey;
    }
    $wcRequestData['sort'][][$sortKey]['order'] = $sortOrder;    
    $filters = array_diff_key($filters, array_flip(['sort','sort_order']));
    if($serviceCategorypro != ''){
        $filters['service_category'] = $serviceCategorypro;
    }
    if($allowChannelpro != ''){
        $filters['allow_channel'] = $allowChannelpro;
    }    
    //DATA FIELDS
    $wcRequestData['stored_fields'][] = "_id";
    $wcRequestData['stored_fields'][] = "_score";
    $wcRequestData['stored_fields'][] = "_source";
    $fltrCntr = 0;
    /** unset service category from filters if filter on zone is true so that 
    it can be modified to match availability_zone filter requirement **/
    if($filterOnZone && !is_null($availabilityZones)) {
        unset($filters['service_category']);
        /*$availValues = (strpos($availabilityZones,"|"))?explode("|",$availabilityZones):$availabilityZones;*/
        $availValues = (strpos($availabilityZones,"|"))?str_replace("|"," ",$availabilityZones):$availabilityZones;
            // $key = ($key == "availability_zones")?"ibo_availability_zone":$key;
        if ($serviceCategorypro != ''  && isset($availValues)) {
            $serviceValues = (strpos($serviceCategorypro,"|"))?explode("|",$serviceCategorypro):$serviceCategorypro;
            $nationalFlag = false;
            $finalValues = [];
            $localExists = strpos($serviceCategorypro, 'LOCAL') !== false ? true:false;
            $regionalExists = strpos($serviceCategorypro, 'REGIONAL') !== false ? true:false;
            $nationalExists = strpos($serviceCategorypro, 'NATIONAL')!== false ? true:false;
            
            if(isset($serviceValues) && is_array($serviceValues)) {
                foreach ($serviceValues as $serviceKey => $value) {
                    if (strtolower($value) !== 'national') {
                        $finalValues[] = $value;
                    }                    
                }
            } else {
                if(strtolower($serviceValues) !== 'national') {
                    $finalValues[] = $serviceValues;
                }
            }                    

            if(!$localExists && !$regionalExists && $nationalExists) {
                $wcRequestData['query']['bool']['must'][$fltrCntr]['term']['service_category'] = 'NATIONAL'; 
                $fltrCntr++; 
            } 

            if (!empty($finalValues)) {
                if(($localExists || $regionalExists) && $nationalExists) {
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][0]['bool']['must']['term']['service_category'] = 'NATIONAL';
                    if(sizeof($finalValues) > 1) {                                
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][]['terms'] = ['service_category' => $finalValues];
                    } else {
                        if(is_array($finalValues)){
                            $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][0]['term'] = ['service_category' => $finalValues[0]];
                        }else{

                        $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][0]['term'] = ['service_category' => $finalValues];
                        }
                    }
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][1]['multi_match']['query'] = $availValues;
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][1]['multi_match']['fields'][] = 'ibo_availability_zone';
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][1]['bool']['must'][1]['multi_match']['operator'] = 'or';
                    /*if(is_array($availValues) && sizeof($availValues) >1) {
                     //print_r($finalValues);die("269");

                        $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][2]['bool']['must']['terms']['ibo_availability_zone'] = $availValues; 
                    } else {

                        $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][2]['bool']['must']['term']['ibo_availability_zone'] = $availValues;
                       // print_r($finalValues);exit;
                    }*/

                    /*$wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][2]['bool']['must']['match']['ibo_availability_zone'] = $availValues;*/
                    $fltrCntr++;
                }

                if(($localExists || $regionalExists) && !$nationalExists) {
                    //print_r($finalValues);exit;
                    if(sizeof($finalValues) > 1) {
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['terms']['service_category'] = $finalValues;
                    } else {
                        if(is_array($finalValues)){
                             $wcRequestData['query']['bool']['must'][$fltrCntr]['term']['service_category'] = $finalValues[0];
                        } else {
                            $wcRequestData['query']['bool']['must'][$fltrCntr]['term']['service_category'] = $finalValues;
                        }
                    }
                    $fltrCntr++;
                   /* if(is_array($availValues) && sizeof($availValues) >1) {
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['terms']['availability_zone_value'] = $availValues; 
                    } else {
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['term']['availability_zone_value'] = $availValues;
                    }*/
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['match']['ibo_availability_zone'] = $availValues; 
                    $fltrCntr++;
                }
            }
        }
    }
     
    if(isset($filters)) { 
        foreach($filters as $key=>$val) {            
            if($key == "price"){ 
                if(strpos($val,"|")) { 
                    $pricesVales = explode("|",$val); 
                    $valCnt = 0;
                    foreach($pricesVales as $prices) { 
                        $val = explode("_",$prices); 
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][$valCnt]['range'][$price_key]['gte'] = $val[0]; 
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['bool']['should'][$valCnt]['range'][$price_key]['lte'] = $val[1]; 
                        $valCnt++;
                    }
                }else{ 
                    $val = explode("_",$val); 
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['range'][$price_key]['gte'] = $val[0]; 
                    if($val[1] != "*" && $val[1] >0) { 
                        $wcRequestData['query']['bool']['must'][$fltrCntr]['range'][$price_key]['lte'] = $val[1]; 
                    } 
                }
                $fltrCntr++;
            } else { 
                $key = ($key == "category_id")?"category_ids":$key; 
                $key = ($key == "allow_channel")?"allowed_channels":$key;
                $val = (strpos($val,"|"))?explode("|",$val):$val; 
                if(isset($val) && is_array($val)) { 
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['terms'][$key] = $val; 
                    $fltrCntr++; 
                } else { 
                    $wcRequestData['query']['bool']['must'][$fltrCntr]['term'][$key] = $val; 
                    $fltrCntr++; 
                } 
            } 
        }
        
    }
    $wcRequestData['collapse']['field']="unique_group_id.keyword"; 
    //print_r(json_encode($wcRequestData)) ;exit;  
    return json_encode($wcRequestData);
}

function addLog($logdata)
{
    $writer = new \Zend\Log\Writer\Stream(dirname(__DIR__) .'/var/log/es_customsearch.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    $logger->info($logdata);  
}