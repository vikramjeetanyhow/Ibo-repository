<?php

namespace Embitel\ProductExport\Controller\Adminhtml\Export;

use Embitel\ProductExport\Cron\ProductExport;
use Embitel\ProductExport\Model\EboExportFactory;
use Embitel\ProductExport\Model\Export\AllProducts;
use Embitel\ProductExport\Model\Export\Products;
use Embitel\ProductExport\Model\ProductExportHelper;
use Embitel\ProductExport\Model\ResourceModel\EboExport;
use Embitel\ProductImport\Controller\Adminhtml\Import;
use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Export extends Import
{

    /**
     * @var type Products
     */
    protected $products;

    /**
     * @var type AllProducts
     */
    protected $allProducts;

    private EboExport $eboExportResourceModel;
    private EboExportFactory $eboExportFactory;
    private ProductExportHelper $productExportHelper;
    private TimezoneInterface $timezone;
    private CollectionFactory $attributeSetCollectionFactory;
    private ProductExport $productExport;

    /**
     * @param Context $context
     * @param Products $products
     * @param AllProducts $allProducts
     * @param FileFactory $fileFactory
     * @param EboExportFactory $eboExportFactory
     * @param EboExport $eboExportResourceModel
     * @param ProductExportHelper $productExportHelper
     * @param TimezoneInterface $timezone
     * @param CollectionFactory $attributeSetCollectionFactory
     */
    public function __construct(
        Context             $context,
        Products            $products,
        AllProducts         $allProducts,
        EboExportFactory    $eboExportFactory,
        EboExport           $eboExportResourceModel,
        ProductExportHelper $productExportHelper,
        TimezoneInterface   $timezone,
        CollectionFactory   $attributeSetCollectionFactory,
        FileFactory $fileFactory
    )
    {
        $this->products = $products;
        $this->allProducts = $allProducts;
        $this->eboExportResourceModel = $eboExportResourceModel;
        $this->eboExportFactory = $eboExportFactory;
        $this->productExportHelper = $productExportHelper;
        $this->timezone = $timezone;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        parent::__construct($context, $fileFactory);
    }

    public function execute()
    {
        $attributeSetId = $this->getRequest()->getParam('attribute_set_id');
        $exportHistoryModel = $this->eboExportFactory->create();
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $fileName = "";
        try {
            if ($attributeSetId == 'all') {
                $exportHistoryModel->setType("all");
                $fileName = "all";
                $fileName = $this->appendTimestamp($fileName);
                $exportHistoryModel->setFileName($fileName);
                $this->eboExportResourceModel->save($exportHistoryModel);
            } else {
                $attributeSets = $this->attributeSetCollectionFactory->create()
                    ->addFieldToFilter('entity_type_id', 4)->load()
                    ->toOptionArray();
                if ($attributeSetId == "all_attribute_set") {
                        foreach($attributeSets as $set) {
                            $exportHistoryModel = $this->eboExportFactory->create();
                            $fileName = $set['value'] . "_" . $set['label'];
                            $exportHistoryModel->setType("attribute_set");
                            $fileName = $this->appendTimestamp($fileName);
                            $exportHistoryModel->setFileName($fileName);
                            $this->eboExportResourceModel->save($exportHistoryModel);
                        }
                } else {
                    $key = array_search( $attributeSetId, array_column($attributeSets, "value"), true);
                    $fileName = $attributeSetId . "_" . $attributeSets[$key]['label'];
                    $exportHistoryModel->setType("attribute_set");
                    $fileName .= "_" . $this->timezone->date()->getTimestamp();
                    $exportHistoryModel->setFileName($fileName);
                    $this->eboExportResourceModel->save($exportHistoryModel);
                }

            }


        } catch (Exception $ex) {
            $this->productExportHelper->addLog("File Name: " . $ex->getFile() . " Line No" . $ex->getLine());
            $this->productExportHelper->addLog($ex->getMessage());
            $this->messageManager->addError($ex->getMessage());
            $resultRedirect->setUrl($this->_redirect->getRedirectUrl());
            return $resultRedirect;
        }
        $this->messageManager->addSuccessMessage("File export scheduled successfully with filename: " . $fileName);
        return $resultRedirect->setUrl($this->_redirect->getRedirectUrl());

    }

    /**
     * @param string $fileName
     * @return string
     */
    private function appendTimestamp(string $fileName): string
    {
        $fileName .= "_" . $this->timezone->date()->getTimestamp();
        return $fileName;
    }
}
