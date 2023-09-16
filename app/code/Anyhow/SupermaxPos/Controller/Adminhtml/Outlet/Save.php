<?php

/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Controller\Adminhtml\Outlet;

use Magento\Backend\App\Action;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Anyhow\SupermaxPos\Model\SupermaxPosOutlet;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var \Anyhow\SupermaxPos\Model\SupermaxPosOutletFactory
     */
    private $outletFactory;

    /**
     * @var \Anyhow\SupermaxPos\Api\OutletRepositoryInterface
     */
    private $outletRepository;

    public function __construct(
        Action\Context $context,
        DataPersistorInterface $dataPersistor,
        \Anyhow\SupermaxPos\Model\SupermaxPosOutletFactory $SupermaxPosOutletFactory = null,
        \Anyhow\SupermaxPos\Api\OutletRepositoryInterface $OutletRepository = null,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Directory\Model\Region $regionFactory
    ) {
        $this->dataPersistor = $dataPersistor;
        $this->SupermaxPosOutletFactory = $SupermaxPosOutletFactory
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Model\SupermaxPosOutletFactory::class);
        $this->OutletRepository = $OutletRepository
            ?: \Magento\Framework\App\ObjectManager::getInstance()->get(\Anyhow\SupermaxPos\Api\OutletRepositoryInterface::class);
        parent::__construct($context);
        $this->resource = $resourceConnection;
        $this->regionFactory = $regionFactory;
    }
	
	/**
     * Authorization level
     *
     * @see _isAllowed()
     */
	protected function _isAllowed()
	{
		return $this->_authorization->isAllowed('Anyhow_SupermaxPos::outlet_save');
	}

    /**
     * Save action
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $outletData = array();
        $connection = $this->resource->getConnection();
        $outletTable = $this->resource->getTableName('ah_supermax_pos_outlet');
        $outletAddressTable = $this->resource->getTableName('ah_supermax_pos_outlet_address');
        $outletCategoryTable = $this->resource->getTableName('ah_supermax_pos_category_to_outlet');
        $outletProductTable = $this->resource->getTableName('ah_supermax_pos_product_to_outlet');
        $regionName = '';
        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            if (isset($data['general']['status']) && $data['general']['status'] === 'true') {
                $data['general']['status'] = SupermaxPosOutlet::STATUS_ENABLED;
            }
            if (empty($data['general']['pos_outlet_id'])) {
                $data['general']['pos_outlet_id'] = null;
            }
            if (!empty($data['general']['region_id'])) {
                $regionName = $this->regionFactory->load($data['general']['region_id'])->getName();
            }
            if(empty($data['product_assignment_basis'])){
                $data['product_assignment_basis'] = 'all';
            }

            /** @var \Anyhow\SupermaxPos\Model\SupermaxPosOutlet $model */
            $model = $this->SupermaxPosOutletFactory->create();

            $id = $data['general']['pos_outlet_id'];
            //$this->getRequest()->getParam('id');
            if ($id) {
                try {
                    $model = $this->OutletRepository->getById($id);
                } catch (LocalizedException $e) {
                    $this->messageManager->addErrorMessage(__('This store data no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            $model->setData($data);

            $this->_eventManager->dispatch(
                'Outlet_Outlet_prepare_save',
                ['Outlet' => $model, 'request' => $this->getRequest()]
            );

            try {
                $stoerId = $id ? $data['general']['store_id'] : $data['store_id'];
                if(!empty($stoerId)) {
                    $sql = "SELECT * FROM $outletTable WHERE store_id = '$stoerId'";
                    if(!empty($id)){
                        $sql .= " AND pos_outlet_id != $id";
                    }
                    $outletData = $connection->query($sql)->fetchAll();
                }
                if(empty($outletData)){
                    if($id) {
                        $previousOutletParentId = '';
                        $outletAddressDatas = $connection->query("SELECT * FROM $outletAddressTable WHERE parent_outlet_id = $id");
                        if(!empty($outletAddressDatas)){
                            foreach($outletAddressDatas as $outletAddressData){
                                $previousOutletParentId = $outletAddressData['parent_outlet_id'];
                            }
                        }
                        if (isset($data['general']['display_payments']) && !empty($data['general']['display_payments'])) {
                            $data['general']['display_payments'] = implode(',', $data['general']['display_payments']);
                        }
                        $where1 = $connection->quoteInto('pos_outlet_id = ?', $id);
                        //$this->OutletRepository->save($model);
                        $query1 = $connection->update($outletTable,
                            ['outlet_name'=> $data['general']['outlet_name'], 'source_code'=> $data['general']['source_code'], 'pos_receipt_id'=> $data['general']['pos_receipt_id'], 'store_id'=> $data['general']['store_id'], 'allowed_ips'=> $data['general']['allowed_ips'], 'email'=> $data['general']['email'], 'status'=> $data['general']['status'], 'inventory_node'=> $data['general']['inventory_node'], 'store_wh_node'=> $data['general']['store_wh_node'], 'outlet_address_type'=> $data['general']['outlet_address_type'], 'product_assignment_basis'=> $data['general']['product_assignment_basis'],'receipt_thermal_status'=> $data['general']['receipt_thermal_status'],
                            'online_payment_popup_status'=> $data['general']['online_payment_popup_status'], 'display_payments'=> $data['general']['display_payments'], 'multi_lot_status'=> $data['general']['multi_lot_status']], $where1 );

                            if(isset($data['general']['pos_restro_receipt_id'])){
                                $connection->update($outletTable, ['pos_restro_receipt_id'=> $data['general']['pos_restro_receipt_id']], $where1 );
                            }

                        if(!empty($previousOutletParentId) && $data['general']['outlet_address_type'] == 1){
                            $where2 = $connection->quoteInto('parent_outlet_id = ?', $id);
                            $query2 = $connection->update($outletAddressTable,
                                ['firstname'=> $data['general']['firstname'], 'lastname'=> $data['general']['lastname'], 'company'=> $data['general']['company'], 'street'=> $data['general']['street'], 'city'=> $data['general']['city'], 'country_id'=> $data['general']['country_id'], 'region'=> $regionName,'region_id'=> $data['general']['region_id'], 'postcode'=> $data['general']['postcode'], 'telephone'=> $data['general']['telephone'],'pan_no'=>$data['general']['pan_no'],'gstin'=>$data['general']['gstin']], $where2 );
                        } elseif(empty($previousOutletParentId) && $data['general']['outlet_address_type'] == 1) {
                            $query2 = $connection->insert($outletAddressTable,
                                ['parent_outlet_id' => $id, 'firstname'=> $data['general']['firstname'], 'lastname'=> $data['general']['lastname'], 'company'=> $data['general']['company'], 'street'=> $data['general']['street'], 'city'=> $data['general']['city'], 'country_id'=> $data['general']['country_id'], 'region'=> $regionName, 'region_id'=> $data['general']['region_id'], 'postcode'=> $data['general']['postcode'], 'telephone'=> $data['general']['telephone'],'pan_no'=> $data['general']['pan_no'],'gstin'=> $data['general']['gstin']]);
                        }

                        if($data['general']['product_assignment_basis'] == 'category'){
                            $outletCategoryDatas = $connection->query("SELECT * FROM $outletCategoryTable WHERE parent_outlet_id = $id")->fetchAll();
                            if(!empty($outletCategoryDatas)){
                                $connection->query("DELETE FROM $outletCategoryTable WHERE parent_outlet_id = $id");
                            }
                            if(isset($data['assign_category']['category_id'])){
                                $categories = $data['assign_category']['category_id'];
                                if(!empty($categories)){
                                    foreach($categories as $key=>$value){
                                        $connection->insert($outletCategoryTable,
                                        ['parent_outlet_id' => $id, 'category_id'=> $value]);
                                    }
                                }
                                $connection->query("DELETE FROM $outletProductTable WHERE parent_outlet_id = $id");
                            }
                        }
                        if($data['general']['product_assignment_basis'] == 'all'){
                            $connection->query("DELETE FROM $outletCategoryTable WHERE parent_outlet_id = $id");
                            $connection->query("DELETE FROM $outletProductTable WHERE parent_outlet_id = $id");
                        }
                        if($data['general']['product_assignment_basis'] == 'product'){
                            $connection->query("DELETE FROM $outletCategoryTable WHERE parent_outlet_id = $id");
                        }

                        $this->messageManager->addSuccessMessage(__('You saved the store data.'));
                        $this->dataPersistor->clear('Outlet');
                        if ($this->getRequest()->getParam('back')) {
                            return $resultRedirect->setPath('*/*/edit', ['id' => $id, '_current' => true]);
                        }
                    } else {
                        if (isset($data['display_payments']) && !empty($data['display_payments'])) {
                            $data['display_payments'] = implode(',', $data['display_payments']);
                        }
                        $query1 = $connection->insert($outletTable,
                        ['outlet_name'=> $data['outlet_name'], 'source_code'=> $data['source_code'], 'pos_receipt_id'=> $data['pos_receipt_id'], 'store_id'=> $data['store_id'], 'allowed_ips'=> $data['allowed_ips'], 'email'=> $data['email'], 'status'=> $data['status'], 'inventory_node'=>  $data['inventory_node'], 'store_wh_node'=>  $data['store_wh_node'], 'outlet_address_type'=> $data['outlet_address_type'], 'product_assignment_basis'=> $data['product_assignment_basis'], 'receipt_thermal_status'=> $data['receipt_thermal_status'] , 'online_payment_popup_status'=> $data['online_payment_popup_status'] , 'display_payments'=> $data['display_payments'], 'multi_lot_status'=> $data['multi_lot_status']]);   
                        $lastOutletId = $connection->lastInsertId();

                        $restroWhere = $connection->quoteInto('pos_outlet_id = ?', $lastOutletId);
                        if(isset($data['pos_restro_receipt_id'])) {
                            $connection->update($outletTable, ['pos_restro_receipt_id'=> $data['pos_restro_receipt_id']], $restroWhere );
                        }

                        if($data['outlet_address_type'] == 1){
                        $query2 = $connection->insert($outletAddressTable,
                            ['parent_outlet_id' => $lastOutletId, 'firstname'=> $data['firstname'], 'lastname'=> $data['lastname'], 'company'=> $data['company'], 'street'=> $data['street'], 'city'=> $data['city'], 'country_id'=> $data['country_id'], 'region'=> $regionName, 'region_id'=> $data['region_id'], 'postcode'=> $data['postcode'], 'telephone'=> $data['telephone'],'pan_no'=>$data['pan_no'],'gstin'=>$data['gstin']]);
                        }

                        // if($data['general']['product_assignment_basis'] == 'category'){
                        //     $categories = $data['assign_category']['category_id'];
                        //     if(!empty($categories)){
                        //         foreach($categories as $key=>$value){
                        //             $connection->insert($outletCategoryTable,
                        //             ['parent_outlet_id' => $lastOutletId, 'category_id'=> $value]);
                        //         }
                        //     }
                        // }
                        $this->messageManager->addSuccessMessage(__('You saved the store data.'));
                        $this->dataPersistor->clear('Outlet');
                        if ($this->getRequest()->getParam('back')) {
                            return $resultRedirect->setPath('*/*/edit', ['id' => $lastOutletId, '_current' => true]);
                        }
                    }
                } else {
                    $this->messageManager->addErrorMessage('This store id is not available.');
                }
                
                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addExceptionMessage($e->getPrevious() ?:$e);
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the Store data.'));
            }

            $this->dataPersistor->set('Outlet', $data);
            return $resultRedirect->setPath('*/*/edit', ['id' => $this->getRequest()->getParam('pos_outlet_id')]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}