<?php

namespace Embitel\Importexportcategory\Controller\Adminhtml\Importcategory;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Registry;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\File\Csv;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\CategoryFactory;
use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem\Io\File;
use Magento\Backend\App\Action\Context;

class Save extends \Magento\Backend\App\Action
{
    /**
     * @var UploaderFactory
     */
    protected $_fileUploaderFactory;

    /**
     * @var Filesystem
     */
    protected $_filesystem;

    /**
     * @var Reader
     */
    protected $_moduleReader;

    /**
     * @var Csv
     */
    protected $_fileCsv;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var CategoryFactory
     */
    protected $_categoryFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var File
     */
    protected $_fileio;

    protected $exist_categories_name = [];
    protected $exist_categories_path = [];
    protected $exist_categories_pathname = [];

    /**
     * @param Registry $registry
     * @param UploaderFactory $fileUploaderFactory
     * @param Filesystem $fileSystem
     * @param Reader $moduleReader
     * @param Csv $fileCsv
     * @param StoreManagerInterface $storeManagerInterface
     * @param CategoryFactory $categoryFactory
     * @param LoggerInterface $logger
     * @param File $fileio
     * @param Context $context
     */
    public function __construct(
        Registry $registry,
        UploaderFactory $fileUploaderFactory,
        Filesystem $fileSystem,
        Reader $moduleReader,
        Csv $fileCsv,
        StoreManagerInterface $storeManagerInterface,
        CategoryFactory $categoryFactory,
        LoggerInterface $logger,
        File $fileio,
        Context $context
    ) {
        $this->_fileUploaderFactory = $fileUploaderFactory;
        $this->_filesystem = $fileSystem;
        $this->_moduleReader = $moduleReader;
        $this->_fileCsv = $fileCsv;
        $this->_storeManager = $storeManagerInterface;
        $this->_categoryFactory = $categoryFactory;
        $this->registry = $registry;
        $this->_logger = $logger;
        $this->_fileio = $fileio;
        parent::__construct($context);
    }

    /**
     * run the action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $this->registry->register('isSecureArea', true);
        try {
            $filepath = $this->_uploadFileAndGetName();
            if ($filepath!='' && file_exists($filepath)) {
                chmod($filepath, 0777);
                $data = $this->_fileCsv->getData($filepath);
                if (isset($data[0]) && !empty($data[0])) {
                    $this->processCategory($data, $filepath);
                } else {
                    $this->messageManager->addError('Data Not Found.');
                    $resultRedirect->setPath('embcategory/*/edit');
                    return $resultRedirect;
                }
            } else {
                $this->messageManager->addError('File not Found.');
                $resultRedirect->setPath('embcategory/*/edit');
                return $resultRedirect;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_logger->debug($e->getMessage());
            $this->messageManager->addError($e->getMessage());
        } catch (\RuntimeException $e) {
            $this->_logger->debug($e->getMessage());
            $this->messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $this->_logger->debug($e->getMessage());
            $this->messageManager->addException($e, __('Something went wrong while saving the category.'));
        }
        $resultRedirect->setPath(
            'embcategory/*/edit',
            [
                '_current' => true
            ]
        );
        return $resultRedirect;
    }

    /**
     * Process category save
     *
     * @param type $data
     * @param type $filepath
     * @return type
     */
    protected function processCategory($data, $filepath)
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $header = $data[0];
        $categorieskey = array_search('categories', $header);
        $categoryidkey = array_search('id', $header);
        $cat_info = $this->getRootCategory();

        $alreadyexist = [];
        $this->collectExistingCategoryInfo();

        foreach ($data as $key => $categoryitem) {
            if ($key!=0) {
                $cat_data = $this->_getKeyValue($categoryitem, $header);
                if (isset($categorieskey) && ($categorieskey!='' || $categorieskey===0)) {
                    $array_key = array_search($categoryitem[$categorieskey], $this->exist_categories_pathname);
                    if ($array_key) {
                        $alreadyexist[] = $categoryitem[$categorieskey];
                    } else {
                        $strmark = strrpos($categoryitem[$categorieskey], '|');
                        $_parentid = '';
                        $newcategory = '';
                        if ($strmark != false) {
                            $parentpath = substr($categoryitem[$categorieskey], 0, ($strmark));
                            $newcategory = substr($categoryitem[$categorieskey], ($strmark)+1);
                            $_parentid = array_search($parentpath, $this->exist_categories_pathname);
                        } else {
                            $newcategory = $categoryitem[$categorieskey];
                            $_parentid = $cat_info->getId();
                        }
                        if ($_parentid != '' && $newcategory != '') {
                            $cateitem = $this->_categoryFactory->create();
                            $cateitem->setData($cat_data);
                            $parentcategory = $this->_categoryFactory->create();
                            $parentcategory->load($_parentid);
                            if ($parentcategory->getId()) {
                                $cateitem->setParentId($_parentid);
                                $cateitem->setPath($parentcategory->getPath());
                            }
                            $cateitem->setAttributeSetId($cateitem->getDefaultAttributeSetId());
                            $cateitem->setName($newcategory);
                            $_url_key = str_replace(' ', '-', strtolower($newcategory));
                            if (in_array($newcategory, $this->exist_categories_name)) {
                                $_url_key .= '-'.mt_rand(10, 99);
                            }
                            $cateitem->setUrlKey($_url_key);
                            $cateitem->setStoreId(0);
                            $cateitem->save();
                            if ($cateitem->getId()) {
                                $this->exist_categories_name[$cateitem->getId()] = trim($cateitem->getName());
                                $this->exist_categories_path[$cateitem->getId()] = trim($cateitem->getPath());
                                $this->exist_categories_pathname[$cateitem->getId()] = trim($categoryitem[$categorieskey]);
                            }
                        }
                    }
                } elseif (isset($categoryidkey) && ($categoryidkey!='' || $categoryidkey===0)) {
                    //update categories
                    $catemodel = $this->_categoryFactory->create();
                    $catemodel->setStoreId(0);
                    $cateitem = $catemodel->load($categoryitem[$categoryidkey]);
                    $nocategoryfound = true;
                    if ($cateitem->getId()) {
                        $nocategoryfound = false;
                        $_parentid = $cateitem->getParentId();
                        foreach ($cat_data as $key => $value) {
                            if (!in_array($key, ['id', 'url_key','url_path','path','level','children_count','full_path'])) {
                                $cateitem->setData($key, $value);
                            }
                        }
                        $parentid = $cateitem->getParentId();
                        if ($parentid!=$_parentid && $cateitem->getId() > 2) {
                            $_catemodel = $this->_categoryFactory->create();
                            $parentcat = $_catemodel->load($parentid);
                            if ($parentcat->getId()) {
                                $cateitem->setPath($parentcat->getPath().'/'.$cateitem->getId());
                            } else {
                                $this->messageManager->addError('Parent category not Found.');
                                $resultRedirect->setPath('embcategory/*/edit');
                                return $resultRedirect;
                            }
                            $cateitem->move($parentid, false);
                        }
                        if ($cateitem->getId() <= 2) {
                            $cateitem->unsetData('posted_products');
                        }
                        $cateitem->save();
                    }
                } else {
                    $this->messageManager->addError('Data Column not Found.');
                    $resultRedirect->setPath('embcategory/*/edit');
                    return $resultRedirect;
                }
            }
        }
        if (isset($alreadyexist) && !empty($alreadyexist)) {
            $this->messageManager->addError(__('This categories are already exist: ').implode(', ', $alreadyexist));
            $this->messageManager->addSuccess(__('Other categories has been imported Successfully'));
        } elseif (isset($categoryidkey) && $categoryidkey===0) {
            if ($nocategoryfound) {
                $this->messageManager->addError(__('No Category Found.'));
            } else {
                $this->messageManager->addSuccess(__('Categories has been updated Successfully'));
            }
        } else {
            $this->messageManager->addSuccess(__('Categories has been imported Successfully'));
        }
        unlink($filepath);
        $this->_session->setEmbitelImportcategoryTestData(false);
        $resultRedirect->setPath('embcategory/*/edit');
        return $resultRedirect;
    }

    /**
     * Get root category id
     *
     * @param type $header
     * @return type
     */
    private function getRootCategory()
    {
        $rootCategoryId = $this->_storeManager->getStore()->getRootCategoryId();
        $rootCat = $this->_categoryFactory->create();
        return $rootCat->load($rootCategoryId);
    }

    /**
     * Collect existing category information
     */
    protected function collectExistingCategoryInfo()
    {
        $categorycollection = $this->_categoryFactory->create()->getCollection()->addAttributeToSelect('name');
        foreach ($categorycollection as $key => $value) {
            $this->exist_categories_name[$value->getId()] = trim($value->getName());
            $this->exist_categories_path[$value->getId()] = trim($value->getPath());
            $checkcat = $this->_categoryFactory->create();
            $categoryobj = $checkcat->load($value->getId());
            $parentcatnames = [];
            $parentid = '';
            foreach ($this->getparentCategories($categoryobj) as $key => $parentcate) {
                $parentcatnames[] = $parentcate->getName();
                $parentid = $parentcate->getId();
            }
            $parent_cat = implode('|', $parentcatnames);
            if ($parent_cat && $parentid) {
                $this->exist_categories_pathname[$parentid] = trim($parent_cat);
            }
        }
    }

    /**
     * Upload file and get name of the file.
     *
     * @return boolean
     */
    protected function _uploadFileAndGetName()
    {
        $uploader = $this->_fileUploaderFactory->create(['fileId' => 'file']);
        $uploader->setAllowedExtensions(['CSV', 'csv']);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        $path = $this->_filesystem->getDirectoryRead(DirectoryList::VAR_DIR)
            ->getAbsolutePath('categoryimport');

        $this->checkAndCreatePath($path);
        $result = $uploader->save($path.'/');
        if (isset($result['file']) && !empty($result['file'])) {
            return $result['path'].$result['file'];
        }
        return false;
    }

    /**
     * Check if path not exist then create it.
     * If path is not writable then update permission.
     *
     * @param type $path
     */
    protected function checkAndCreatePath($path)
    {
        try {
            if (!is_dir($path)) {
                $this->_fileio->mkdir($path, 0777, true);
                $this->_fileio->chmod($path, 0777, true);
            }
            if (!$this->_fileio->isWriteable($path)) {
                $this->_fileio->chmod($path, 0777, true);
            }
        } catch (\Exception $ex) {
            $this->_logger->debug("Path not writable:" . $ex->getMessage());
        }
    }

    /**
     * Get category key values.
     *
     * @param type $row
     * @param type $headerArray
     * @return type
     */
    protected function _getKeyValue($row, $headerArray)
    {
        $temp = [];
        foreach ($headerArray as $key => $value) {
            if ($value == 'image') {
                $temp[$value] = $this->_getImagePath($row[$key]);
            } elseif ($value == 'products' && $row[$key] != '') {
                $temp['posted_products'] = array_flip(explode('|', $row[$key]));
            } else {
                $temp[$value] = $row[$key];
            }
        }
        return $temp;
    }

    /**
     * Get image path
     *
     * @param type $categoryimage
     * @return type
     */
    protected function _getImagePath($categoryimage)
    {
        $weburl = strpos($categoryimage, 'http://');
        if ($weburl!==false) {
            $imagepath = $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath('catalog/category');
            $this->checkAndCreatePath($imagepath);
            $file = file_get_contents($categoryimage);
            if ($file!='') {
                $allowed =  ['gif','png' ,'jpg', 'jpeg'];
                $ext = strtolower(pathinfo($categoryimage, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    $imagename = pathinfo($categoryimage, PATHINFO_BASENAME);
                    $imagepath = $imagepath.'/'.$imagename;
                    $result = file_put_contents($imagepath, $file);
                    if ($result) {
                        return $imagename;
                    }
                }
            }
        } else {
            return $categoryimage;
        }
    }

    /**
     * Get parent category information
     *
     * @param type $category
     * @return type
     */
    protected function getparentCategories($category)
    {
        $pathIds = array_reverse(explode(',', $category->getPathInStore()));
        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categories */
        $categories = $this->_categoryFactory->create()->getCollection();
        return $categories->setStore(
            $this->_storeManager->getStore()
        )->addAttributeToSelect(
            'name'
        )->addAttributeToSelect(
            'url_key'
        )->addFieldToFilter(
            'entity_id',
            ['in' => $pathIds]
        )->load()->getItems();
    }
}
