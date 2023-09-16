<?php
/**
 * @desc: For Ibo wallet integration
 * @package Embitel_Customer
 * @author Amar Jyoti
 *
 */
namespace Embitel\Customer\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Setup\Exception;
use Magento\Variable\Model\Variable;
use Magento\Eav\Model\Config;
use Magento\Customer\Api\GroupRepositoryInterface;

class Wallet
{

    private Curl $curl;
    private ScopeConfigInterface $scopeConfig;
    private $connection;
    private Variable $customVariable;

    public function __construct(
        Curl $curl,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        Variable $variable,
        Config $eavConfig,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->customVariable = $variable;
        $this->eavConfig = $eavConfig;
        $this->groupInterface = $groupRepository;
    }

    /**
     * @desc For get Auth token
     * @return mixed|void
     */
    public function createToken()
    {

        //check token in custom variable
        $customVariableData = $this->customVariable->loadByCode('ibo_wallet_token', 'base');

        if(!empty($customVariableData)) {
            $token = $customVariableData->getHtmlValue();
            //$tokenTtl = $customVariableData->getPlainValue();

            if(!empty($token)) {
                //check ttl logic here with current time.
                return $token;
            }
        }

        $url = $this->scopeConfig->getValue("ibo_wallet/ibo_wallet_api/wallet_token_api");
        $xAuthToken = $this->scopeConfig->getValue("ibo_wallet/ibo_wallet_api/wallet_x_auth_token");
        $this->addLog("=== Calling Get token API === ");
        if (!empty($url) && !empty($xAuthToken)) {
            $headers = [
                "cache-control" => "no-cache",
                "x-auth-token" => $xAuthToken,
                "Content-Length" => 0
            ];
            $tokenRes = json_decode($this->getCurlCall($url, $headers, "GET"), true);

            if (!empty($tokenRes['token'])) {
                $customVariableData->setHtmlValue($tokenRes['token']);
                $customVariableData->save();
                return $tokenRes['token'];
            }
        } else {
            $this->addLog("Please configure Token url and Auth key.");
        }
    }

    /**
     * @desc For create new wallet
     * @param $data
     * @return void
     */
    public function createWallet($data = null)
    {
        try {
            $enable = $this->scopeConfig->getValue("ibo_wallet/ibo_wallet_api/is_enable");

            if ($enable) {
                $token = $this->createToken();

                if (!empty($token)) {
                    $this->addLog("=== Calling Create wallet API === ");
                    $this->addLog("Request Data: ".json_encode($data));
                    $url = $this->scopeConfig->getValue("ibo_wallet/ibo_wallet_api/wallet_create_api");
                    $headers = [
                        "cache-control" => "no-cache",
                        "authorization" => $token,
                        "content-type" => "application/json",
                        "x-channel-id" => "WEB"
                    ];

                    $payload = [
                        "customer_id" => $data['customer_id'] ?? '',
                        "customer_group" => $this->getCustomerGroup($data) ?? '',
                        "phone_number" => [
                            "country_code" => "+91",
                            "number" => $data['mobilenumber'] ?? ''
                        ]
                    ];

                    $walletCreateRes = json_decode($this->getCurlCall($url, $headers, "POST", $payload), true);

                    if (!empty($walletCreateRes['wallet_id'])) {
                        $this->addLog("Wallet created successfully. Id: " . $walletCreateRes['wallet_id']);

                        return $walletCreateRes;
                    } else {
                        //on failed, insert record to temp table
                        $this->addLog("Wallet creation failed.");
                        $this->insertFailedRecord($data);
                    }

                } else {
                    $this->addLog("Wallet Token API failed.");
                    $this->insertFailedRecord($data);
                }
            }
        } catch (\Exception $ex) {
            $this->addLog("Error in request: ".$ex->getMessage());
        }
    }

    /**
     * @desc For common curl call
     * @param $url
     * @param $headers
     * @param $method
     * @param $payload
     * @return string|void
     */
    public function getCurlCall($url, $headers, $method, $payload = [])
    {
        try {
            $this->curl->setOption(CURLOPT_RETURNTRANSFER, true);
            $this->curl->setOption(CURLOPT_POST, true);
            $this->curl->setOption(CURLOPT_TIMEOUT, 30);
            $this->curl->setOption(CURLOPT_MAXREDIRS, 10);
            $this->curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $this->curl->setHeaders($headers);
            $this->addLog("Url: " . $url);
            $this->addLog("Headers: " . json_encode($headers));
            $this->addLog("Payload: " . json_encode($payload));
            if ($method == 'POST') {
                $this->curl->post($url, json_encode($payload));
            } elseif ($method == "GET") {
                $this->curl->get($url);
            }
            $result = $this->curl->getBody();
            $this->addLog("Response: " . $result);
            return $result;
        } catch (\Exception $ex) {
            $this->addLog("Error in curl call: ");
        }
    }

    public function addLog($logData)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/ibo_wallet.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($logData);
    }

    public function insertFailedRecord($data)
    {
        try {
            $tableName = $this->connection->getTableName('ibo_customer_wallet');
            $select = $this->connection->select()
                ->from(
                    ['c' => $tableName],
                    ['*']
                )->where('customer_id = ?', $data['customer_id']);
            $record =  $this->connection->fetchRow($select);

            if ($record) {
                $retryAttempt = $record['retry_attempt'] + 1;
                $this->connection->update(
                    "ibo_customer_wallet",
                    ['retry_attempt'=> $retryAttempt],
                    'customer_id=' . $data['customer_id']
                );
            } else {
                $failedData = ['customer_id' => $data['customer_id']];
                $this->connection->insertOnDuplicate("ibo_customer_wallet", $failedData);
            }
        } catch (\Exception $ex) {
            $this->addLog("Error in Mysql insert");
        }
    }

    /**
     * @param $data
     * @return string
     * @desc Get customer group name.
     */
    public function getCustomerGroup($data) {
        try {
            $customerGroup = $this->groupInterface->getById($data['customer_group_id']);
            return $customerGroup->getCode();
        } catch (\Exception $ex) {
            $this->addLog("Error in Customer group: ".$ex->getMessage());
        }
    }
}
