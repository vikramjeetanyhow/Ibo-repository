<?php
namespace Embitel\PostCode\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Embitel\CustomerGraphQl\Model\Customer\Address\PostCode;

class PostCodeGraphql implements ResolverInterface
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
        PostCode $postCode,
        array $data = []
    ) {
        $this->file = $file;
        $this->csv = $csv;
        $this->dir = $dir;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
        $this->postCode = $postCode;
    }
 /**
  * @param Field $field
  * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
  * @param ResolveInfo $info
  * @param array|null $value
  * @param array|null $args
  * @return array|\Magento\Framework\GraphQl\Query\Resolver\Value|mixed
  * @throws GraphQlInputException
  */
    public function getCsvData($args)
    {
        $data = [];
        $csvFile = $this->dir->getRoot() . '/pincode.csv'; // CSV file path
        try {
            if ($this->file->isExists($csvFile)) {
                $this->csv->setDelimiter(",");
                $data = $this->csv->getData($csvFile);
                $filterBy = $args['pincode'];
                $output = [];
                $pincode = [];
                $pincode = array_filter($data, function ($data) use ($filterBy) {
                    return ($data[0] == $filterBy);
                });

                if (!isset($pincode) || empty($pincode)) {
                    throw new GraphQlInputException(__('Please enter a valid pincode'));

                } else {
                    foreach ($pincode as $value) {
                        $output['pincode'] = $value[0];
                        $output['state'] = $value[1];
                        $output['city'] = $value[2];
                        $regionId = $this->getRegionCode($output['state']);
                        $output['region_id'] = $regionId;
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
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {

        if (!isset($args['pincode']) || empty($args['pincode'])) {
          throw new GraphQlInputException(__('Please enter correct pincode'));
        } elseif (!preg_match("/^[1-9][0-9]{5}$/", $args['pincode'])) {
          throw new GraphQlInputException(__('Please enter a valid pincode (Ex: 626117).'));
        } else {
            //return $this->getCsvData($args);
            //MAG-1695: Now Post code will read from 'pincode' table. not from csv.
            $args['postcode'] = $args['pincode'];
            return $this->postCode->getCsvData($args);
        }
    }
    /**
     * @param string $region
     * @return string[]
     */
    public function getRegionCode(string $region)
    {
        $regionCode = $this->collectionFactory->create()
            ->addRegionNameFilter($region)
            ->getFirstItem()
            ->toArray();
        return $regionCode['region_id'];
    }
}
