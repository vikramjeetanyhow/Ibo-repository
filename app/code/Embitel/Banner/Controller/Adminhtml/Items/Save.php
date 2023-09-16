<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Controller\Adminhtml\Items;

use Magento\Framework\Exception\LocalizedException;

class Save extends \Embitel\Banner\Controller\Adminhtml\Items
{
   
    public function execute()
    {
        if ($this->getRequest()->getPostValue()) {
            try {
                
                $data = $this->getRequest()->getPostValue();
                
                if(isset($_FILES['products_sku']['name']) && $_FILES['products_sku']['name'] != '' && $data['banner_type']==2) {                    
                    try{                        
                        $this->csvParser->setDelimiter(",");
                        $products_sku = $this->csvParser->getData($_FILES['products_sku']['tmp_name']);                        
                        if (!empty($products_sku)) {
                            $count = 0;
                            $listSkus = [];
                            if($_FILES['products_sku']['type']==='text/csv'){
                                foreach (array_slice($products_sku, 1) as $key => $value) {
                                    $count++;
                                    $listSkus[]=trim($value['0']);               
                                }   
                                if ($count>0 && is_array($listSkus)){                   
                                    $data['products_sku'] = implode(",",$listSkus); //$this->serializer->serialize($listSkus);
                                } 
                             }else{
                                throw new LocalizedException(__('Invalid Formated File')); 
                            }                             

                        } else {                            
                            throw new LocalizedException(__('File hase been empty'));
                        }
                    } catch (\Exception $other) {                           
                        $this->messageManager->addError($other->getMessage());
                        
                        $id = (int)$this->getRequest()->getParam('banner_id');
                        
                        if ($id) {  
                                                  
                            $this->_redirect('embitel_banner/*/edit', ['id' => $id]);
                        } else {                   
                                                   
                            $this->_redirect('embitel_banner/*/new');
                        }
                        return;                  
                       
                    }
                }
                               
                if(isset($_FILES['mobile_image']['name']) && $_FILES['mobile_image']['name'] != '') {
                    try{
                        $uploaderFactory = $this->uploaderFactory->create(['fileId' => 'mobile_image']);
                        $uploaderFactory->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                        $imageAdapter = $this->adapterFactory->create();
                        $uploaderFactory->addValidateCallback('custom_image_upload',$imageAdapter,'validateUploadFile');
                        $uploaderFactory->setAllowRenameFiles(true);
                        $uploaderFactory->setFilesDispersion(true);
                        $mediaDirectory = $this->filesystem->getDirectoryRead($this->directoryList::MEDIA);
                        $destinationPath = $mediaDirectory->getAbsolutePath('embitel/banner');
                        $result = $uploaderFactory->save($destinationPath);
                        if (!$result) {
                            throw new LocalizedException(
                                __('File cannot be saved to path: $1', $destinationPath)
                            );
                        }
                        
                        $imagePath = 'embitel/banner'.$result['file'];
                        $data['mobile_image'] = $imagePath;
                    } catch (\Exception $e) {
                    }
                }
                if(isset($_FILES['desktop_image']['name']) && $_FILES['desktop_image']['name'] != '') {
                    try{
                        $uploaderFactory = $this->uploaderFactory->create(['fileId' => 'desktop_image']);
                        $uploaderFactory->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);
                        $imageAdapter = $this->adapterFactory->create();
                        $uploaderFactory->addValidateCallback('custom_image_upload',$imageAdapter,'validateUploadFile');
                        $uploaderFactory->setAllowRenameFiles(true);
                        $uploaderFactory->setFilesDispersion(true);
                        $mediaDirectory = $this->filesystem->getDirectoryRead($this->directoryList::MEDIA);
                        $destinationPath = $mediaDirectory->getAbsolutePath('embitel/banner');
                        $result = $uploaderFactory->save($destinationPath);
                        if (!$result) {
                            throw new LocalizedException(
                                __('File cannot be saved to path: $1', $destinationPath)
                            );
                        }
                        
                        $imagePath = 'embitel/banner'.$result['file'];
                        $data['desktop_image'] = $imagePath;
                    } catch (\Exception $e) {
                    }
                }
                if(isset($data['mobile_image']['delete']) && $data['mobile_image']['delete'] == 1) {
                    $mediaDirectory = $this->filesystem->getDirectoryRead($this->directoryList::MEDIA)->getAbsolutePath();
                    $file = $data['mobile_image']['value'];
                    $imgPath = $mediaDirectory.$file;
                    if ($this->_file->isExists($imgPath))  {
                        $this->_file->deleteFile($imgPath);
                    }
                    $data['mobile_image'] = NULL;
                }
                if(isset($data['desktop_image']['delete']) && $data['desktop_image']['delete'] == 1) {
                    $mediaDirectory = $this->filesystem->getDirectoryRead($this->directoryList::MEDIA)->getAbsolutePath();
                    $file = $data['desktop_image']['value'];
                    $imgPath = $mediaDirectory.$file;
                    if ($this->_file->isExists($imgPath))  {
                        $this->_file->deleteFile($imgPath);
                    }
                    $data['desktop_image'] = NULL;
                }
                if (isset($data['mobile_image']['value'])){
                    $data['mobile_image'] = $data['mobile_image']['value'];
                }
                if (isset($data['desktop_image']['value'])){
                    $data['desktop_image'] = $data['desktop_image']['value'];
                }
                if (isset($data['cat_ids']) && is_array($data['cat_ids']) && $data['banner_type']==1){                   
                    $data['cat_ids'] = implode(",",$data['cat_ids']);
                    $data['products_sku'] = '';
                }else{
                    $data['cat_ids'] = '';
                }
                if (isset($data['attribute_ids']) && is_array($data['attribute_ids'])){
                    $data['attribute_ids'] = implode(",",$data['attribute_ids']);
                }else{
                    $data['attribute_ids'] = '';
                }
                if (isset($data['customer_group']) && is_array($data['customer_group'])){                   
                    $data['customer_group'] = implode(",",$data['customer_group']);
                }else{
                    $data['customer_group'] = '';
                }                
                if (isset($data['banner_type'])){
                    $data['banner_type'] = $data['banner_type'];
                }
                $inputFilter = new \Zend_Filter_Input(
                    [],
                    [],
                    $data
                );
                $data = $inputFilter->getUnescaped();
                $id = $this->getRequest()->getParam('banner_id');
                $bannerModel = $this->bannerFactory;
                $model = $bannerModel->create();                
                if ($id) {
                    $model->load($id);                   
                    if ($id != $model->getId()) {
                        throw new \Magento\Framework\Exception\LocalizedException(__('The wrong item is specified.'));
                    }
                }
                              
                $model->setData($data);              
                $model->save();
                $session = $this->backendSession;
                $session->setPageData($model->getData());
                
                $this->messageManager->addSuccess(__('You saved the item.'));
                $session->setPageData(false);
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('embitel_banner/*/edit', ['id' => $model->getId()]);
                    return;
                }
                $this->_redirect('embitel_banner/*/');
                return;
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->messageManager->addError($e->getMessage());
                $id = (int)$this->getRequest()->getParam('id');
                if (!empty($id)) {
                    $this->_redirect('embitel_banner/*/edit', ['id' => $id]);
                } else {                   
                    $this->_redirect('embitel_banner/*/new');
                }
                return;
            } catch (\Exception $e) {
                $this->messageManager->addError(
                    __($e->getMessage().'Something went wrong while saving the item data. Please review the error log.')
                );
                $this->logger->critical($e);
                $this->backendSession->setPageData($data);
                $this->_redirect('embitel_banner/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }
        }
        $this->_redirect('embitel_banner/*/');
    }
}
