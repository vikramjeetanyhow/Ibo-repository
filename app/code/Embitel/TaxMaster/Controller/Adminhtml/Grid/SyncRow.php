<?php
/**
 * Embitel TaxMaster List Controller.
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Controller\Adminhtml\Grid;

use Magento\Framework\Controller\ResultFactory;
use \Embitel\TaxMaster\Model\GetTaxApi;
use \Embitel\TaxMaster\Model\TaxUpdateRepository;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class SyncRow extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Framework\Registry
     */
    private $coreRegistry;

    /**
     * @var \Embitel\TaxMaster\Model\GridFactory
     */
    private $gridFactory;

    /**
     * @var $getTaxApi
     */
    protected $getTaxApi;

    /**
     * @var $taxUpdateRepository
     */
    protected $taxUpdateRepository;
    /**
     * @var $timezoneInterface
     */
    protected $timezoneInterface;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Embitel\TaxMaster\Model\GridFactory $gridFactory
     * @param GetTaxApi $getTaxApi
     * @param TaxUpdateRepository $taxUpdateRepository
     * @param TimezoneInterface $timezoneInterface
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Embitel\TaxMaster\Model\GridFactory $gridFactory,
        GetTaxApi $getTaxApi,
        TaxUpdateRepository $taxUpdateRepository,
        TimezoneInterface $timezoneInterface
    ) {
        parent::__construct($context);
        $this->coreRegistry = $coreRegistry;
        $this->gridFactory = $gridFactory;
        $this->getTaxApi = $getTaxApi;
        $this->taxUpdateRepository = $taxUpdateRepository;
        $this->timezoneInterface = $timezoneInterface;
    }

    /**
     * Mapped Grid List page.
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        $rowId = (int)$this->getRequest()->getParam('id');
        $updatedTime = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        $rowData = $this->gridFactory->create();
        if ($rowId) {
            $rowData = $rowData->load($rowId);
            $rowHsnCode = $rowData->getHsnCode();
            $taxRate = '';
            $curlData = $this->getTaxApi->getTax($rowHsnCode);
            try {
                if (isset($curlData->tax->tax_rate)) {
                    $taxRate = $curlData->tax->tax_rate;
                    $rowData->setData(
                        ['id' => $rowData->getId(),
                         'tax_class_id' => $taxRate,
                         'updated_at' => $updatedTime
                        ]
                    );
                    $rowData->save();
                    $this->taxUpdateRepository->save($rowHsnCode, $taxRate);
                    $this->messageManager->addSuccessMessage(__('Row has been synced successfully.'));
                } else {
                    $this->messageManager->addErrorMessage(__('Tax class does not found.'));
                }
            } catch (\Exception $e) {
                $message = 'This row Couldn\'t synced, please try again.' . $e->getMessage();
                if ($e->getMessage()) {
                    $message = $e->getMessage();
                }
                $this->messageManager->addErrorMessage(__($message));
            }
            $this->coreRegistry->register('row_data', $rowData);
            $this->_redirect('grid/grid/index');
            return;
        }
    }

    /**
     * Check record delete Permission.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Embitel_TaxMaster::add_row');
    }
}
