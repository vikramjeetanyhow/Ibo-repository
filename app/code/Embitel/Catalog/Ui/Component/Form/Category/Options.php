<?php
namespace Embitel\Catalog\Ui\Component\Form\Category;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Registry;

/**
* Options tree for "Categories" field
*/
class Options implements OptionSourceInterface
{

    protected $categoryCollectionFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var array
     */
    protected $categoryTree;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    protected $_registry;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param RequestInterface $request
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        RequestInterface $request,
        StoreManagerInterface $_storeManager,
        Registry $registry
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->request = $request;
        $this->_storeManager = $_storeManager;
        $this->_registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return $this->getCategoryTree();
    }


    /**
     * Retrieve categories tree
     *
     * @return array
     */
    protected function getCategoryTree()
    {    
        if ($this->categoryTree === null) {

            $rootCategoryId = $this->_storeManager->getStore(1)->getRootCategoryId();
            $currentCategoryId = $this->getCurrentCategory()->getId();
            $serviceCategory = $this->getSelectedServiceCategory($currentCategoryId);
            $categoryById = [];

            $categories = $this->categoryCollectionFactory->create()                              
                    ->addAttributeToSelect(['entity_id','name']);
            $categories->addFieldToFilter('path', array('like'=> "1/$rootCategoryId/%"));

             if($serviceCategory !=''){       
                $categories->addAttributeToFilter('service_category',$serviceCategory);
            }

            if (count($categories)>0) {        
                foreach ($categories as $category) {
                    $categoryId = $category->getEntityId();
                    if (!isset($categoryById[$categoryId])) {
                        $categoryById[$categoryId] = [
                            'value' => $categoryId
                        ];
                    }
                    $categoryById[$categoryId]['label'] = $category->getName();
                }
            }else{
                    $categoryById = "<option value=''>--Category Not Available--</option>";
                }
            $this->categoryTree = $categoryById;
        }
        return $categoryById;
    }

    /**
     * Retrieve current category id
     *
     * @return array
     */
    public function getCurrentCategory()
    {        
        return $this->_registry->registry('current_category');
    }

    public function getSelectedServiceCategory($currentCategoryId) {

        $serviceCategoryValue = null;
        $collection = $this->categoryCollectionFactory
                        ->create()
                        ->addAttributeToSelect('service_category')
                        ->addAttributeToFilter('entity_id',['eq'=>$currentCategoryId])
                        ->setPageSize(1);

        $catObj = $collection->getFirstItem();
        $catData = $catObj->getData();
        if(isset($catData['service_category'])) {
            $serviceCategoryValue = $catData['service_category'];
        }
        return $serviceCategoryValue;
    }
}