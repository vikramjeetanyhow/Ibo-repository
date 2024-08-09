<!DOCTYPE html>
<?php
use Magento\Framework\App\Bootstrap;
require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get(Magento\Framework\App\State::class);
$state->setAreaCode('frontend');

if(isset($_POST['quantity'])){
    $quantity = $_POST['quantity'];
}
if(isset($_POST['entity_id'])){
    $productsIds = explode(',',$_POST['entity_id']);
}

$objectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
$scopeConfig = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface');
$storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
$helper = $objectManager->get('Anyhow\SupermaxPos\Helper\Data');
$baseCurrencyCode = $storeManager->getStore()->getCurrentCurrencyCode();
$barcodeMediaPath = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'supermax/barcode/products/';
$headerLogoMediaPath = $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'supermax/barcode/logo/';
$barcodeMediaImageDirPath = $objectManager->get('Magento\Framework\Filesystem')->getDirectoryRead('Magento\Framework\App\Filesystem\DirectoryList'::MEDIA)->getAbsolutePath('supermax/barcode/products/');

$pos_barcode_print_label_size_unit = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_size_unit');
$pos_barcode_print_label_width = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_width');
$pos_barcode_print_label_height = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_height');
$pos_barcode_print_label_margin_top = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_top') ? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_top').'px' : '0px';
$pos_barcode_print_label_margin_right = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_right') ? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_right').'px' : '0px';
$pos_barcode_print_label_margin_bottom = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_bottom') ? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_bottom').'px' : '0px';
$pos_barcode_print_label_margin_left = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_left') ? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_margin_left').'px' : '0px';

$pos_barcode_print_label_image_width = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_print_image_width')? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_print_image_width').'px' : 'auto' ;

$pos_barcode_print_label_image_height = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_print_image_height') ?$scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_print_image_height').'px' : 'auto';

$pos_barcode_print_label_number_font_size = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_print_code_font_size');
$pos_barcode_print_label_content_font_size = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_print_content_font_size');

$pos_barcode_print_label_currency_code = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_printing_configutaion/ah_supermax_pos_barcode_label_price_currency');

if($pos_barcode_print_label_currency_code == ''){
    $pos_barcode_print_label_currency_code =  $storeManager->getStore()->getCurrentCurrencyCode();
}
$currencySymbol = $objectManager->get('Magento\Framework\Locale\CurrencyInterface')->getCurrency($pos_barcode_print_label_currency_code)->getSymbol();

$sortOrder = array();

// display product name
$displayProductNameStatus = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_product_name_status');
$productNameSortOrder = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_product_name_sortorder');
if((bool)$displayProductNameStatus){
    $sortOrder['name'] = $productNameSortOrder;
}

// display product price
$displayProductPriceStatus = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_product_price_status');
$productPriceSortOrder = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_product_price_sortorder');
if((bool)$displayProductPriceStatus){
    $sortOrder['price'] = $productPriceSortOrder;
}

// header logo
$headerLogoStatus = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_logo_status');
$headerLogo = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_label_Header_logo');
$headerLogoWidth = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_label_Header_logo_width') ? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_label_Header_logo_width').'px' : 'auto';
$headerLogoHeight = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_label_Header_logo_height') ? $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_label_Header_logo_height').'px' : 'auto';
$headerLogoSortOrder = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_logo_settings/ah_supermax_pos_barcode_logo_sortorder');
if((bool)$headerLogoStatus){
    $sortOrder['header_logo'] = $headerLogoSortOrder;
}

// product attributes
$productAttributesStatus = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_attribute_settings/ah_supermax_pos_barcode_product_attribute_status');

$allowedProductAttrString = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_attribute_settings/ah_supermax_pos_barcode_product_custom_attributes');

$allowedProductAttributes = explode(',',$scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_attribute_settings/ah_supermax_pos_barcode_product_custom_attributes'));
$productAttributesSortOrder = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_attribute_settings/ah_supermax_pos_barcode_product_attributes_sortorder');
if((bool)$productAttributesStatus){
    $sortOrder['attributes'] = $productAttributesSortOrder;
}

// display barcode image
$displayBarcodeImageStatus = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_image_status');
$barcodeImageSortOrder = $scopeConfig->getValue('ah_supermax_pos_barcode_printing_configuration/ah_supermax_pos_barcode_label_product_settings/ah_supermax_pos_barcode_image_sortorder');
if((bool)$displayBarcodeImageStatus){
    $sortOrder['barcode_image'] = $barcodeImageSortOrder;
}
asort($sortOrder);

$product = array();
$faultIds = array();
foreach($productsIds as $productId){
    $attributes = array();
    $productData = $objectManager->create('Magento\Catalog\Model\Product')->load($productId);
    if(!empty($productData)){

        if(empty($productData->getBarcode())){
            $faultIds[] = $productData->getId();
        }

        if(!empty($allowedProductAttrString)){
            foreach($allowedProductAttributes as $attributeCode){
                if ($productData->getData($attributeCode)) { 
                    $attributes[] = array(
                            'attribute_label'=> $productData->getResource()->getAttribute($attributeCode)->getFrontendLabel(),
                            'attribute_values' => $productData->getResource()->getAttribute($attributeCode)->getFrontend()->getValue($productData)
                    );
                }
            }
        }
        
        $barcodeImage = '';
        if(file_exists($barcodeMediaImageDirPath.$productData->getBarcode().'.jpg')){
            $barcodeImage = $barcodeMediaPath.$productData->getBarcode().'.jpg';
        }
        $specialPrice = null;
        if($productData->getSpecialPrice()){
            $specialPrice = $currencySymbol.number_format((float)$helper->convert($productData->getSpecialPrice(),$baseCurrencyCode, $pos_barcode_print_label_currency_code),2);
        }
        $products[] = array(
            'id' => $productData->getId(),
            'name'=> $productData->getName(),
            'price' => $currencySymbol.number_format((float)$helper->convert($productData->getPrice(),$baseCurrencyCode, $pos_barcode_print_label_currency_code),2),
            'special_price' => $specialPrice,
            'header_logo' => $headerLogoMediaPath.$headerLogo,
            'barcode' => $productData->getBarcode(),
            'barcode_image' => $barcodeImage,
            'attributes' => $attributes
        );
    }
}
?>
<html>
<head>
<meta charset="UTF-8" />
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<style>
.error{
    color: red;
}
    .barcode-box {
        display: inline-block; 
        margin: 10px 10px; 
    }
    @media print {
        body { 
            margin: 0 ; 
        }

        @page {
            size: <?php echo $pos_barcode_print_label_width.$pos_barcode_print_label_size_unit.' '.$pos_barcode_print_label_height.$pos_barcode_print_label_size_unit; ?> ;
            margin: 0;
        }

        .barcode-box {
            display: block ;
            page-break-after: always ;
            page-break-before: always ;
            margin-top: <?php echo $pos_barcode_print_label_margin_top; ?> ;
            margin-right: <?php echo $pos_barcode_print_label_margin_right; ?> ;
            margin-bottom: <?php echo $pos_barcode_print_label_margin_bottom; ?> ;
            margin-left: <?php echo $pos_barcode_print_label_margin_left; ?> ;
        }

        .header-logo {
            text-align: center;
        }

        .header-logo img {
            margin: 0px ;
            padding: 0px ;
            width: <?php echo $headerLogoWidth; ?> ;
            height: <?php echo $headerLogoHeight; ?> ;
        }

        .img {
            /* text-align: center; */
            font-size: <?php echo $pos_barcode_print_label_number_font_size; ?>px ;
            margin-top: 1px ;
        }

        .img img {
            vertical-align: middle ;
            
            margin-bottom: 1px ;
            width: <?php echo $pos_barcode_print_label_image_width; ?> ;
            height: <?php echo $pos_barcode_print_label_image_height; ?> ;
        }

        .content {
            /* margin-bottom: 1px ; */
            font-size: <?php echo $pos_barcode_print_label_content_font_size; ?>px ;
        }

        .content label {
            font-weight: 600 ;
        }
    }
</style>
</head>
<body><?php 
$flag = false;
if(!empty($faultIds) && (bool)$displayBarcodeImageStatus) {?>
    <div class ="error">
        <?php echo "<b>Error: </b> Generate barcode for product Ids: ". implode(',', $faultIds) .' or Disable barcode display settings'; ?>
    </div><?php 
    die();
}
if(!empty($products)){
    foreach ($products as $product) {
        for ($i = 1; $i <= $quantity ; $i++) {?>
            <div class="barcode-box">
                <div class="content"><?php 
                    if(!empty($sortOrder)){
                        foreach($sortOrder as $key=>$value){
                            switch($key){
                                case 'header_logo': 
                                    if($product['header_logo']) { ?>
                                        <div class="header-logo">
                                            <img src="<?php echo $product['header_logo'] ; ?>" alt="logo">
                                        </div><?php 
                                    }
                                break; ?>
                                <?php
                                case 'name': 
                                    if($product['name']) { ?>
                                        <div>
                                            <label><?php echo 'Name'; ?>: </label>
                                            <?php echo $product['name']; ?>
                                        </div><?php 
                                    }
                                break;
                                case 'price': 
                                    if($product['price']) { ?>
                                        <div>
                                            <label><?php echo 'Price'; ?>: </label><?php 
                                            if($product['special_price']) { ?>
                                                <span>
                                                    <?php echo $product['special_price']; ?>
                                                </span><?php 
                                            } else { ?>
                                                <span>
                                                    <?php echo $product['price']; ?>
                                                </span><?php 
                                            } ?>
                                        </div><?php 
                                    }
                                break;
                                case 'attributes':
                                    if (!empty($product['attributes'])) { 
                                        foreach($product['attributes'] as $attribute){?>
                                            <div>
                                                <label><?php echo $attribute['attribute_label'];?>: </label>
                                                    <span>
                                                        <?php echo $attribute['attribute_values']; ?>
                                                    </span>
                                            </div><?php
                                        }
                                    }
                                break;
                                case 'barcode_image': 
                                    if($product['barcode']){?>
                                        <div class="img"><?php
                                            if($product['barcode_image']){ ?>
                                                <div>
                                                    <img src="<?php echo $product['barcode_image'] ?>">
                                                </div><?php 
                                            }?>
                                            <label><?php echo $product['barcode']; ?></label>
                                        </div><?php
                                    }
                                break;
                            }
                        }
                    } else {
                        $flag = true;
                    }?>
                </div>
            </div><?php 
        } 
    } 
} else {
    $flag = true;
}

if($flag){?>
    <div class ="error">
        <?php echo "<b>Error: Set Configuration Settings</b> "; ?>
    </div><?php
    die();
}?>
    <script>
        $(document).ready(function() {
                    window.print();
            });
    </script>
</body>
</html>