<?php
namespace Ibo\MrpUpdate\Model;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use \Ibo\MrpUpdate\Model\MrpUpdateInMage;
use \Ibo\MrpUpdate\Helper\Logger as MRPLogger;

class MrpPrice
{
    /**
     * @var Request
     */
    protected $request;
       
    /**
     * @var MrpUpdateInMage
     */
    private $_mrpUpdateInMage;

    /**
     * @var MRPLogger
     */
    private $_mrpLogger;

    /**
     * @var Construct
     * @param string $request
     * @param string $_mrpUpdateInMage
     */
    public function __construct(
        Request $request,
        MrpUpdateInMage $_mrpUpdateInMage,
        MRPLogger $_mrpLogger
    ) {
        $this->request = $request;
        $this->_mrpUpdateInMage = $_mrpUpdateInMage;
        $this->_mrpLogger = $_mrpLogger;
    }
    /**
     * @inheritdoc
     */
    public function save()
    {
        if ($this->request->getBodyParams() == null) {
            throw new LocalizedException(__('Request paramters are empty'));
        }

        $inputParameters = $this->request->getBodyParams();
        $this->_mrpLogger->addLog('MRP Input Parameters');
        $this->_mrpLogger->addLog($inputParameters);

        if (!isset($inputParameters['sku']) || empty($inputParameters['sku'])) {
            $this->_mrpLogger->addLog('SKU cannot be empty');
            throw new LocalizedException(__('SKU cannot be empty'));
        }

        if (!isset($inputParameters['mrp']) || count($inputParameters['mrp']) == 0) {
            $this->_mrpLogger->addLog('MRP cannot be empty. SKU: '.$inputParameters['sku']);
            throw new LocalizedException(__('MRP cannot be empty'));
        }

        $mrps = $inputParameters['mrp'];

        foreach ($mrps as $key => $value) {
            if (empty($value['value'])) {
                $this->_mrpLogger->addLog('MRP value cannot be null. SKU: '.$inputParameters['sku']);
                throw new LocalizedException(__('MRP value cannot be null'));
            } elseif (!is_numeric($value['value'])) {
                $this->_mrpLogger->addLog('MRP value must be numeric only. SKU: '.$inputParameters['sku']);
                throw new LocalizedException(__('MRP value must be numeric only'));
            }
        }

        $mageResponse = $this->_mrpUpdateInMage->Update($inputParameters);

        $returnArray = json_encode($mageResponse);

        return $returnArray;
    }
}
