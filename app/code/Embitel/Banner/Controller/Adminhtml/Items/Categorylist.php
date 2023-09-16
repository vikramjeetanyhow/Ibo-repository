<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com 
 */

namespace Embitel\Banner\Controller\Adminhtml\Items;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Json\Helper\Data as HelperData;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class Categorylist extends \Magento\Backend\App\Action
{

    protected $resultPageFactory;
    protected $scopeConfig;
    protected $_storeManager;
    protected $_helperData;
    protected $_collectionFactory;
    protected $excludeCategoryIds = [];

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        ScopeConfigInterface $scopeConfig,
        HelperData $helperData,
        CollectionFactory $collectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,       
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->resultPageFactory = $resultPageFactory;
        $this->_storeManager = $storeManager;
        $this->_helperData = $helperData;
        $this->_collectionFactory = $collectionFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        if($this->getRequest()->isAjax()){      

            $displayzone = $this->getRequest()->getParam('displayzone');
            $catids = $this->getRequest()->getParam('catids');
            $rootCategoryId = $this->_storeManager->getStore()->getRootCategoryId();
            $excludeCategories = $this->scopeConfig->getValue("cate/cat_config/exclude_cagegory");
            $this->excludeCategoryIds[] = $rootCategoryId;
            if ($excludeCategories!='') {
               $this->excludeCategoryIds =  array_merge($this->excludeCategoryIds,explode(',', $excludeCategories));
            }
            $categories = $this->_collectionFactory->create()                              
                ->addAttributeToSelect(['entity_id','name'])
                ->addFieldToFilter('path', array('like'=> "1/$rootCategoryId/%"));
            if(!empty($displayzone) && $displayzone != 'LOCAL'){
                $categories->addAttributeToFilter('service_category',$displayzone);
            }   
            
            if($catids == NULL){
                $catids = [];
            }else{
                $catids = explode(',', $catids) ;
            }

            $selected = '';
            $categorylist = "";
            
            if ($displayzone != '' && count($categories)>0) {            
                foreach ($categories as $_category) {
                    if(in_array($_category->getId(),$this->excludeCategoryIds)){ continue; }
                    if(in_array($_category->getEntityId(), $catids)) { $selected = 'selected'; } else{  $selected = ''; }
                    $categorylist .= "<option value='".$_category->getEntityId()."'  ".$selected." >" .$_category->getName(). "</option>";
                    }
            }else{
                $categorylist .= "<option value=''>--Category Not Available--</option>";
            }
            $result['htmlconent'] = $categorylist;
            $this->getResponse()->representJson(
                $this->_helperData->jsonEncode($result)
            );
        }
    }

    /**
     * Determine if authorized to perform group actions.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return true;
    }
  }