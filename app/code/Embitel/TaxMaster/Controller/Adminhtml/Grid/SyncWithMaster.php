<?php
/**
 * Embitel TaxMaster List Controller.
 * @category  Embitel
 * @package   Embitel_TaxMaster
 * @author    Embitel
 */
namespace Embitel\TaxMaster\Controller\Adminhtml\Grid;

use \Embitel\TaxMaster\Model\ResourceModel\TaxMaster\CollectionFactory as TaxMasterCollection;
use Magento\Framework\Message\ManagerInterface;
use \Embitel\TaxMaster\Model\GetTaxApi;
use \Embitel\TaxMaster\Model\TaxUpdateRepository;

class SyncWithMaster extends \Magento\Backend\App\Action
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
     * @var TaxMasterCollection
     */
    protected $taxMasterFactory;
    /**
     * @var $_eavAttribute
     */
    protected $_eavAttribute;

    /**
     * @var $getTaxApi
     */
    protected $getTaxApi;

    /**
     * @var $taxUpdateRepository
     */
    protected $taxUpdateRepository;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Embitel\TaxMaster\Model\GridFactory $gridFactory
     * @param TaxMasterCollection $taxMasterFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute $eavAttribute
     * @param ManagerInterface $messageManager
     * @param GetTaxApi $getTaxApi
     * @param TaxUpdateRepository $taxUpdateRepository
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Embitel\TaxMaster\Model\GridFactory $gridFactory,
        TaxMasterCollection $taxMasterFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute $eavAttribute,
        ManagerInterface  $messageManager,
        GetTaxApi $getTaxApi,
        TaxUpdateRepository $taxUpdateRepository
    ) {
        parent::__construct($context);
        $this->coreRegistry = $coreRegistry;
        $this->gridFactory = $gridFactory;
        $this->taxMasterFactory = $taxMasterFactory;
        $this->_eavAttribute = $eavAttribute;
        $this->messageManager = $messageManager;
        $this->getTaxApi = $getTaxApi;
        $this->taxUpdateRepository = $taxUpdateRepository;
    }

    /**
     * Mapped Grid List page.
     *
     * @return bool
     */
    public function execute()
    {
        $hsnCodeId = $this->_eavAttribute->getIdByCode('catalog_product', 'hsn_code');
        $taxMasterCollection = $this->gridFactory->create()->getCollection()
            ->addFieldToSelect(['hsn_code','id']);

        if ($taxMasterCollection->getSize() > 0) {
            foreach ($taxMasterCollection as $hsnData) {
                $taxMaster = $this->gridFactory->create();
                $hsnData = $hsnData->getData();
                $taxRate = '';
                $curlData = $this->getTaxApi->getTax($hsnData['hsn_code']);
                if (isset($curlData->tax->tax_rate)) {
                    $taxRate = $curlData->tax->tax_rate;
                }
                try {
                    if (count($hsnData) && isset($hsnData['id'])) {
                        $taxMaster->setData(['id' => $hsnData['id'], 'tax_class_id' => $taxRate]);
                        $taxMaster->save();
                        $this->taxUpdateRepository->save($hsnData['hsn_code'], $taxRate);
                    }
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage($e, __('Couldn\'t save record'));
                }
            }
        }
        $this->messageManager->addSuccessMessage(__('Records has been synced with master successfully.'));
        $this->_redirect('grid/grid/index');
    }
}
