<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require realpath(__DIR__) . '/../app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');
$headers = [];
if (session_status() == PHP_SESSION_NONE) {    
    session_start();
}
if (!function_exists('getallheaders')) {
  foreach ($_SERVER as $name => $value) {
    if (substr($name, 0, 5) == 'HTTP_') {
      $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
    }
  }
} else {
  $headers = getallheaders();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($headers['access-token']) && ($headers['access-token'] == "MTYzNzE0NDE2NDM5MHRva2Vu")) {
  echo "\nStart:\n";
  try {
    $resource = $objectManager->get("Magento\Framework\App\ResourceConnection");
    $connection= $resource->getConnection();
    $posUserTable = $resource->getTableName("ah_supermax_pos_user");
    $posOutletTable = $resource->getTableName("ah_supermax_pos_outlet");
    $encryptor = $objectManager->get("Magento\Framework\Encryption\EncryptorInterface");
    $st002Password = $encryptor->getHash("karnataka23", false);
    $st003Password = $encryptor->getHash("chennai23", false);
    $st004Password = $encryptor->getHash("raniganj23", false);
    $connection->query("UPDATE $posUserTable SET `password`='$st002Password' WHERE `pos_outlet_id`=(SELECT `pos_outlet_id` FROM $posOutletTable WHERE `store_id`='ST002')");
    echo "--------------------------\n";
    echo "ST002 User's password has been reset\n";
    $connection->query("UPDATE $posUserTable SET `password`='$st003Password' WHERE `pos_outlet_id`=(SELECT `pos_outlet_id` FROM $posOutletTable WHERE `store_id`='ST003')");
    echo "--------------------------\n";
    echo "ST003 User's password has been reset\n";
    $connection->query("UPDATE $posUserTable SET `password`='$st004Password' WHERE `pos_outlet_id`=(SELECT `pos_outlet_id` FROM $posOutletTable WHERE `store_id`='ST004')");
    echo "--------------------------\n";
    echo "ST004 User's password has been reset\n";
    echo "--------------------------\n";
  } catch (Exception $e) {
    echo "There is some error: " . $e->getMessage();
  }
} else{
  http_response_code(400);
  die;
}
echo "Done.\n";