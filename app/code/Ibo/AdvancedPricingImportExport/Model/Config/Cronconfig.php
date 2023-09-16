<?php
namespace Ibo\AdvancedPricingImportExport\Model\Config;

use Magento\AdvancedPricingImportExport\Model\Import\AdvancedPricing as ImportAdvancedPricing;
use Magento\Catalog\Model\Product as CatalogProduct;
use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;
use Magento\ImportExport\Model\Export;
use Magento\Store\Model\Store;
use Magento\Tax\Model\Calculation as TaxCalculation;
use Magento\Tax\Model\Calculation\Rate as TaxCalculationRate;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Export Advanced Pricing
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Cronconfig extends \Magento\Framework\App\Config\Value
{
    public const CRON_STRING_PATH = 'crontab/default/jobs/advancedPricing_export/schedule/cron_expr';
    public const CRON_MODEL_PATH = 'crontab/default/jobs/advancedPricing_export/run/model';

    /**
     * @var \Magento\Framework\App\Config\ValueFactory
     */

    protected $_configValueFactory;

    /**
     * @var mixed|string
     */

    protected $_runModelPath = '';

    /**
     * CronConfig1 constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Config\ValueFactory $configValueFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param string $runModelPath
     * @param array $data
     */

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '', 
        array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After save method
     *
     * @return CronConfig1
     * @throws \Exception
     */

    public function afterSave()
    {
        $time = $this->getData('groups/settings/fields/cron_setting/value');
        $cronExprString = $time;

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH
            )->save();
        } catch (\Exception $e) {
            throw new \Exception(__('Some Thing Want Wrong , We can\'t save the cron expression.'));
        }
        return parent::afterSave();
    }
}
