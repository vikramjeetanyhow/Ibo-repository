<?php
/**
 * Embitel TaxMaster Record Delete Controller.
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Controller\Adminhtml\Grid;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Embitel\TaxMaster\Model\ResourceModel\Grid\CollectionFactory;
use Embitel\TaxMaster\Model\GridFactory;
use \Embitel\TaxMaster\Model\GetTaxApi;
use \Embitel\TaxMaster\Model\TaxUpdateRepository;

class MassSynced extends \Magento\Backend\App\Action
{
    /**
     * Massactions filter.
     * @var Filter
     */
    protected $_filter;

    /**
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @var GridFactory
     */
    private $gridFactory;

    /**
     * @var GetTaxApi
     */
    protected $getTaxApi;

    /**
     * @var $taxUpdateRepository
     */
    protected $taxUpdateRepository;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param GridFactory $gridFactory
     * @param GetTaxApi $getTaxApi
     * @param TaxUpdateRepository $taxUpdateRepository
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        GridFactory $gridFactory,
        GetTaxApi $getTaxApi,
        TaxUpdateRepository $taxUpdateRepository
    ) {

        $this->_filter = $filter;
        $this->_collectionFactory = $collectionFactory;
        $this->gridFactory = $gridFactory;
        $this->getTaxApi = $getTaxApi;
        $this->taxUpdateRepository = $taxUpdateRepository;

        parent::__construct($context);
    }

    /**
     * Execute function to Delete records
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $collection = $this->_filter->getCollection($this->_collectionFactory->create());
        $recordSynced = 0;
        foreach ($collection->getItems() as $record) {
            $taxMaster = $this->gridFactory->create();
            $taxRate = '';
            $curlData = $this->getTaxApi->getTax($record['hsn_code']);
            if (isset($curlData->tax->tax_rate)) {
                $taxRate = $curlData->tax->tax_rate;
            }
            try {
                    $taxMaster->setData(['id' => $record['id'], 'tax_class_id' => $taxRate]);
                    $taxMaster->save();
                    $this->taxUpdateRepository->save($record['hsn_code'], $taxRate);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e, __('Couldn\'t sync the record'));
            }
            $recordSynced++;
        }
        $this->messageManager->addSuccess(__('Total %1 record(s) have been synced with master.', $recordSynced));

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }

    /**
     * Check Category Map record delete Permission.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Embitel_TaxMaster::row_data_delete');
    }
}
