<?php
error_reporting(0);
ini_set('display_errors', 'Off');

if($_SERVER['HTTP_HOST'] == "magento.ibo.com" || $_SERVER['HTTP_HOST'] == "commerce.ibo.com") {
    //require __DIR__ . '/../var/search/staging/es.php';
    $eshost = '10.170.0.11';
    $categoryEsIndex = 'magento2_category';
    $productEsIndex = 'magento2_product_1';
    $attributeEsIndex = 'magento2_attribute';
} elseif($_SERVER['HTTP_HOST'] == "magento-staging.ibo.com" || $_SERVER['HTTP_HOST'] == "commerce-staging.ibo.com") {
    //require __DIR__ . '/../var/search/staging/es.php';
    $eshost = '10.170.0.13';
    $productEsIndex = 'magento2_stg_product_1';
 } else { 
    //require __DIR__ . '/../var/search/es.php';
    $eshost = '127.0.0.1';
    $productEsIndex = 'magento2_product_1';
 }

// header("Cache-Control: no-cache, must-revalidate");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
require_once '../vendor/autoload.php';
require realpath(__DIR__) . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();
$dateTime = $objectManager->get('\Magento\Framework\Stdlib\DateTime\TimezoneInterface');
$date = $dateTime->date();
$gmtDate = $date->format('Y-m-d H:i:s');
if ($_SERVER['REQUEST_METHOD'] === 'GET' && count($_GET) >0) {
    $returnResponse = array();
    $requestBody = $_GET; 
    $priceRegionSwitch = true;
    if($priceRegionSwitch) { 
        $region = (isset($requestBody['price_zone']))?strtolower($requestBody['price_zone']):"default"; 
        $defaultCustomerGroupID = "10_".$region;//NEED TO CHANGE DYNAMIC DEFAULT CUSTOMER GROUP FROM CONFIG
        $customerGroupId = (isset($requestBody['customer_group_id']))?$requestBody['customer_group_id']."_".$region."_1":"";
    } else { 
        $defaultCustomerGroupID = "10";//NEED TO CHANGE DYNAMIC DEFAULT CUSTOMER GROUP FROM CONFIG
        $customerGroupId = (isset($requestBody['customer_group_id']))?$requestBody['customer_group_id']."_1":"";
    }    
    $default_price_key = "price_".$defaultCustomerGroupID."_1"; //Pass based on default group 
    $customer_price_key = ($customerGroupId != "")?"price_".$customerGroupId:$default_price_key; //Pass based on customer group
    $price_key = ($customerGroupId != "")?$customer_price_key:$default_price_key;
    $custGroupId = (isset($requestBody['customer_group_id']))?$requestBody['customer_group_id']:"10";    
    $currentPageOld = (isset($requestBody['page']))? $requestBody['page']:1; //for use the original request in below
    $currentPage = (isset($requestBody['page']) && $requestBody['page'] >1 )? $requestBody['page'] - 1:0; //Doing substract as in ES doc start with 0
    $pageSize = (isset($requestBody['page_size']))?$requestBody['page_size']:10;    
    $requestBody['is_published'] = (isset($requestBody['is_published']))?$requestBody['is_published']:1;
    $filterOnZone = isset($requestBody['filter_on_availability_zones']) ? $requestBody['filter_on_availability_zones'] : false;
    $availabilityZones = (isset($requestBody['availability_zones']))?$requestBody['availability_zones']:null;
    $serviceCategorypro = (isset($requestBody['service_category']))?$requestBody['service_category']:"";

    $filters = (isset($requestBody))?$requestBody:"";
    //Unset known keys which is not filter like sort, page etc.
    $remove = ['customer_group_id', 'sort','page', 'page_size','price_zone','filter_on_availability_zones','availability_zones']; 
    $filters = array_diff_key($filters, array_flip($remove));

    $requestData = array();
    //LIMIT & PAGINATION
    $currentPage = ($currentPage > 0)?($pageSize*$currentPage):$currentPage;
    $requestData['from'] = $currentPage;
    $requestData['size'] = $pageSize;
    
    //DATA FIELDS
    $requestData['stored_fields'][] = "_id";
    $requestData['stored_fields'][] = "_score";
    $requestData['stored_fields'][] = "_source";
    
    //NEED TO FETCH Dynamic config 
    $elasticaClient = new \Elastica\Client(array( 
        'host' => $eshost, 
        'port' => 9200 
    )); 
    

    //QUERY 
    $cntr = 0;   
    $fltrCntr = 0;
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
                    $fltrCntr++;
                }

                if(($localExists || $regionalExists) && !$nationalExists) {
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
                    $requestData['query']['bool']['must'][$fltrCntr]['match']['ibo_availability_zone'] = $availValues; 
                    $fltrCntr++;
                }
            }
        }
    }
    if(isset($filters)) { 
        foreach($filters as $key=>$val) {
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
    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bd-esindexer.log');
    $logger = new \Zend\Log\Logger();
    $logger->addWriter($writer);
    $bestDealList = "SELECT `main_table`.sku FROM `ibo_home_bestdeal` AS `main_table` WHERE customer_group = '".$custGroupId."' AND (`from_date` <= '".$gmtDate."') AND (((((`to_date` >= '".$gmtDate."') OR (`to_date` IS null)))))";

    $bestDealListResult = $connection->fetchAll($bestDealList);
    $skus = [];
    if(count($bestDealListResult) > 0){
        $skus = array_column($bestDealListResult, 'sku');        
        $requestData['query']['bool']['must'][]['terms']['sku'] = $skus;
        $fltrCntr++;
    }else{
        $totalCount = count($bestDealListResult); 
        $returnResponse['data']['products']['total_count'] = $totalCount;
                   
        $returnResponse['data']['products']["page_info"]['page_size'] = (int)$pageSize;
        $returnResponse['data']['products']["page_info"]['current_page'] = (int)$currentPageOld;
        $totalPages = ($totalCount > $pageSize)?ceil($totalCount/$pageSize):1;
        $returnResponse['data']['products']["page_info"]['total_pages'] = $totalPages;        
        //Item foreach Start 
        $returnResponse['data']['products']['items'] = array();
        echo json_encode($returnResponse); 
        exit;
    }
    //$logger->info('------bestDealList before start------');
    //$logger->info(print_r($skus,true));
    
    $requestData['query']['bool']['must'][$fltrCntr]['term']["product_type_id"] = "simple"; //As we need to fetch only simple skus 
    //COLAPSE BY PARENT 
    $requestData['collapse']['field']="unique_group_id.keyword"; 
    try{  
        //$logger->info(print_r(json_encode($requestData),true));       
        //Need to fetch dynamic index 
        $index = $elasticaClient->getIndex($productEsIndex); 
        $path = $index->getName() . '/_search'; 
        $response = $elasticaClient->request($path, 'POST', json_encode($requestData)); 
        $responseArray = $response->getData(); 
        // print_r($responseArray);die;
        //$logger->info(print_r($responseArray,true));
       
        if(isset($responseArray) ) { 
            $totalCount = $responseArray['hits']['total']['value']; 
            $returnResponse['data']['products']['total_count'] = $totalCount;      
                       
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
                $returnResponse['data']['products']['items'][$iCnt]['id'] = $product['_id'];
                $returnResponse['data']['products']['items'][$iCnt]['unique_group_id'] = $productData['unique_group_id']; 
                $returnResponse['data']['products']['items'][$iCnt]['name'] = (isset($productData['meta_title']))?$productData['meta_title']:$productData['name']; 
                $returnResponse['data']['products']['items'][$iCnt]['esin'] = $productData['esin'];
                $returnResponse['data']['products']['items'][$iCnt]['slug'] = (isset($productData['slug']))?$productData['slug']:"";
                $returnResponse['data']['products']['items'][$iCnt]['brand_Id'] = (isset($productData['brand_Id']))?(string)$productData['brand_Id']:""; 
                $returnResponse['data']['products']['items'][$iCnt]['sku'] = $productData['sku'];
                $returnResponse['data']['products']['items'][$iCnt]['mrp'] = (isset($productData['mrp']))?$productData['mrp']:"";
                $returnResponse['data']['products']['items'][$iCnt]['per_unit_price_applicable'] = (isset($productData["per_unit_price_applicable"]))?$productData["per_unit_price_applicable"]:NULL;//"PER_UNIT_PRICE_APPLICABLE"; 
                $returnResponse['data']['products']['items'][$iCnt]['per_unit_price_divisor'] = (isset($productData["per_unit_price_divisor"]))?$productData["per_unit_price_divisor"]:NULL;//"PER_UNIT_PRICE_DIVISOR"; 
                $returnResponse['data']['products']['items'][$iCnt]['per_unit_price_unit'] = (isset($productData["per_unit_price_unit"]))?$productData["per_unit_price_unit"]:NULL;//"PER_UNIT_PRICE_UNIT"; 
                if(isset($productData["per_unit_price_divisor"])) { 
                    $returnResponse['data']['products']['items'][$iCnt][$productData["per_unit_price_divisor"]] =  (isset($productData[$productData["per_unit_price_divisor"]]))?$productData[$productData["per_unit_price_divisor"]]:"";//"COVERAGE"; 
                }
                $returnResponse['data']['products']['items'][$iCnt]['url_key'] = $productData['url_key']; 
                $returnResponse['data']['products']['items'][$iCnt]['is_published'] = $productData['is_published']; 
                $returnResponse['data']['products']['items'][$iCnt]['allowed_channels'] = $productData['allowed_channels'][1];  
                $returnResponse['data']['products']['items'][$iCnt]['courier_type'] = (isset($productData['ibo_courier_type']))?(string)$productData['ibo_courier_type']:"";
                $returnResponse['data']['products']['items'][$iCnt]['availability_zone'] = (isset($productData['ibo_availability_zone']))?json_decode($productData['ibo_availability_zone'],true):"";
                $returnResponse['data']['products']['items'][$iCnt]['no_of_variants'] = isset($variantCountData[$productData['unique_group_id']]) ? $variantCountData[$productData['unique_group_id']]:1;//$productData["variant_count"];//"NO_OF_VARIANTS"; 
                $taxRate = (isset($productData['tax_class_id_value']))?(int)$productData['tax_class_id_value']:0;
                //PRICE_RANGE as per defaut customer group qty 1 price
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['final_price']['value'] = $productData[$default_price_key]/1; 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $productData[$default_price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                } 
                $productData['mrp'] = (isset($productData['mrp']) && $productData['mrp'] > 0)?(int)$productData['mrp']:0;
                $productData[$default_price_key] = (int)$productData[$default_price_key];
                $difference = $productData['mrp'] - $productData[$default_price_key]; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['discount']['amount_off'] = $amountOff;
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['discount']['percent_off'] = $percOff;        
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $productData[$default_price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                } 
                $ebo_price_range_without_tax = $productData[$default_price_key] * 100 / (100 + $taxRate); 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['final_price']['value'] = round($ebo_price_range_without_tax,2); 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                $difference = $productData['mrp'] - $ebo_price_range_without_tax; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }            
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['discount']['amount_off'] = $amountOff;
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['discount']['percent_off'] = $percOff;             
                if(isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalPrice = $ebo_price_range_without_tax;
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_range']['price_without_tax']['minimum_price']['unit_price'] = $finalUnitPrice;
                }
            
                //PRice Tier based on customer group price/null if not there 
                $tierPrice_without_tax = $productData[$price_key] * 100 / (100 + $taxRate); 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['final_price']['value'] = $productData[$price_key]/1;//"CUSTOMER_GROUP_PRICE";  
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['final_price']['currency'] = "INR"; 
                $difference = $productData['mrp'] - $productData[$price_key]; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                } 
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['discount']['amount_off'] = $amountOff;
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['discount']['percent_off'] = $percOff; 
                if(isset($productData[$price_key]) && isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"]) && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) {  
                    $finalPrice = $productData[$price_key];
                    $finalUnitPrice = ceil($finalPrice / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_with_tax']['minimum_price']['unit_price'] = $finalUnitPrice; 
                }     
            
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['final_price']['value'] = round($tierPrice_without_tax,2);  
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['final_price']['currency'] = "INR";
                $difference = $productData['mrp'] - $tierPrice_without_tax; 
                $amountOff = 0; 
                $percOff = 0;
                if($difference > 0) { 
                    $amountOff = round($difference, 2); 
                    $percOff = round(($difference / $productData['mrp']) * 100);
                }             
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['discount']['amount_off'] = $amountOff;
                $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['discount']['percent_off'] = $percOff;             
                if(isset($productData[$price_key]) && isset($productData["per_unit_price_applicable"]) && isset($productData["per_unit_price_divisor"])  && isset($productData[$productData["per_unit_price_divisor"]]) && isset($productData["per_unit_price_unit"])) { 
                    $finalUnitPrice = ceil($tierPrice_without_tax / $productData[$productData["per_unit_price_divisor"]]).' / '.$productData["per_unit_price_unit"];
                    $returnResponse['data']['products']['items'][$iCnt]['ebo_price_tiers']['price_without_tax']['minimum_price']['unit_price'] = $finalUnitPrice;
                }
                $returnResponse['data']['products']['items'][$iCnt]['image_custom']['url'] = (isset($productData['base_image_custom'])) ? $productData['base_image_custom'] : '';
                $returnResponse['data']['products']['items'][$iCnt]['image']['url'] = $productData['image_url'];
                $returnResponse['data']['products']['items'][$iCnt]['image']['position'] = 0;
                $iCnt++;
        } 
        //Item foreach End
    } 
    //$logger->info('------bestDealList before end------');
        echo json_encode($returnResponse); 
    }catch(Exception $e){ 
        http_response_code(500); 
        print_r($e->getMessage()); die;
    }
}else{
    http_response_code(400);
    // throw new Exception("Bad Request.");
    die;
}

