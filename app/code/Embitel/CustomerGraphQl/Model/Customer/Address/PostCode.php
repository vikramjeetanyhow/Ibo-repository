<?php
namespace Embitel\CustomerGraphQl\Model\Customer\Address;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use PHPUnit\Exception;

class PostCode
{
    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $file;
    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csv;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
       /**
        * @var \Magento\Store\Model\StoreManagerInterface
        */
    public $storeManager;
      /**
       * @var \Magento\Framework\Filesystem\DirectoryList
       */
    protected $dir;
    /**
     * @var Collection
     */
    private $collectionFactory;

    private $csvFile = NULL;
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Filesystem\Driver\File        $file
     * @param \Magento\Framework\File\Csv                      $csv
     * @param \Psr\Log\LoggerInterface                         $logger
     * @param array                                            $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Filesystem\Driver\File $file,
        \Magento\Framework\File\Csv $csv,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Psr\Log\LoggerInterface $logger,
        CollectionFactory $collectionFactory,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Magento\Framework\App\ResourceConnection $resource,
        array $data = []
    ) {
        $this->file = $file;
        $this->csv = $csv;
        $this->dir = $dir;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
        $this->_resource = $resource;
    }
 /**
  * @param Pincode
  */
 /*
    public function getCsvData($args)
    {
        $data = [];
        $this->getCsvFile();
        try {
            if ($this->file->isExists($this->csvFile)) {
                $this->csv->setDelimiter(",");
                $data = $this->csv->getData($this->csvFile);
                $filterBy = $args['postcode'];
                $output = [];
                $pincode = [];
                $pincode = array_filter($data, function ($data) use ($filterBy) {
                    return ($data[0] == $filterBy);
                });

                if (!isset($pincode) || empty($pincode)) {
                    throw new GraphQlInputException(__('Please enter a valid pincode. Provided(' . $filterBy . ')'));
                } else {
                    foreach ($pincode as $value) {
                        $output['pincode'] = isset($value[0]) ? $value[0] : '';
                        $output['state'] = isset($value[1]) ? $value[1] : '';
                        $output['city'] = isset($value[2]) ? $value[2] : '';
                        $region = $this->getRegionCode($output['state']);
                        $output['region_id'] = !empty($region['region_id']) ? $region['region_id'] : 0;
                        $output['region_code'] = !empty($region['code']) ? $region['code'] : '';
                        return $output;
                    }
                }
            } else {
              throw new GraphQlInputException(__('CSV file does not exist'));
            }
        } catch (FileSystemException $e) {
            $this->logger->info($e->getMessage());
            return false;
        }
    }
    */

    /**
     * @ticket: MAG-1695: Pincode migration
     * @desc: Now Pincode will read from 'pincode' table.
     * Earlier it was reading from CSV file.
     * @param $args
     * @return Array
     * @author Amar Jyoti.
     */
    public function getCsvData($args) {

        try {
            $pincode = $args['postcode'] ?? '';
            $pincodeQuery = "SELECT pincode,statename,city,districtname FROM
                                               `pincode` WHERE `pincode` = '$pincode'";
            $pincodeRow = $this->_resource->getConnection()->fetchRow($pincodeQuery);

            if (empty($pincodeRow)) {
                throw new GraphQlInputException(__('Please enter a valid pincode. Provided(' . $pincode . ')'));
            } else {
                $output['pincode'] = $pincodeRow['pincode'] ?? '';
                $output['state'] = $pincodeRow['statename'] ?? '';
                $output['city'] = $pincodeRow['city'] ?? '';

                $region = $this->getRegionCode($output['state']);
                $output['region_id'] = !empty($region['region_id']) ? $region['region_id'] : 0;
                $output['region_code'] = !empty($region['code']) ? $region['code'] : '';
                return $output;
            }

        } catch (Exception $e) {
            $this->logger->info($e->getMessage());
            return false;
        }
    }

    private function getCsvFile(){
        if($this->csvFile == NULL){
            $this->csvFile = $this->dir->getRoot() . '/pincode.csv'; // CSV file path
        }
    }

    /**
     * @param string $region
     * @return string[]
     */
    private function getRegionCode(string $region)
    {
        $regionCode = $this->collectionFactory->create()
            ->addRegionNameFilter($region)
            ->getFirstItem()
            ->toArray();
        return $regionCode;
    }

    /**
     * CustomerType
     */
    public function isValidCustomerInsurance($customerType, $args)
    {
        $validCEType = [
            'insurance_opt_in',
            'relationship_with_nominee',
            'gender',
            'name_of_insured',
            'nominee_name',
            'date_of_birth'
        ];
        if(!empty($customerType) && !empty($args)
            && !in_array($customerType,['Contractor','Expert / Technician'])
            ){
                foreach($validCEType as $attributeCode){
                    if(!empty($args[$attributeCode])){
                        throw new GraphQlInputException(__(implode(',',$validCEType) . ' fields allowed only for customer type "Contractor" and "Expert / Technician"'));
                    }
                }
        }
    }

    /**
     * validate insurance for expert technician
     */
    public function isValidExpertInsurance($customerType, $args)
    {
        $validCEType = [
            'referrer_name',
            'referrer_phone_number'
        ];
        if(!empty($customerType) && !empty($args)
            && !in_array($customerType,['Expert / Technician'])
            ){
                foreach($validCEType as $attributeCode){
                    if(!empty($args[$attributeCode])){
                        throw new GraphQlInputException(__(implode(',',$validCEType) . ' fields allowed only for customer type "Expert / Technician"'));
                    }
                }
        }
    }
}
