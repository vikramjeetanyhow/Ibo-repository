<?php

namespace Ibo\MultiSlider\Model\Primary\ResourceModel;

use Ibo\MultiSlider\Model\Secondary\ResourceModel\ResourceHeroSliderData;
use Ibo\MultiSlider\Model\Secondary\SecondaryHeroSliderDataFactory;
use Ibo\MultiSlider\Model\Secondary\SecondaryHeroSliderData;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class HeroResourceModel extends AbstractDb
{
    /**
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    protected $secondaryResourceModel;
    protected $secondaryModel;
    protected $serializer;
    protected $request;
    /**
     * @SuppressWarnings(PHPMD.LongVariable)
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        SerializerInterface $serializer,
        SecondaryHeroSliderDataFactory $secondaryModel,
        ResourceHeroSliderData $secondaryResourceModel,
        $connectionName = null
    ) {
        $this->secondaryResourceModel = $secondaryResourceModel;
        $this->secondaryModel = $secondaryModel->create();
        $this->serializer = $serializer;
        $this->request = $request;
        parent::__construct($context, $connectionName);
    }
    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _construct()
    {
        $this->_init('category_hero_slider_primary', 'id');
    }

    /**
     * Process page data before saving
     *
     * @param AbstractModel $object
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if (!$this->getIsUniqueBlockToStores($object)) {
            throw new LocalizedException(
                __('A banner slider identifier with the same properties already exists.')
            );
        }
        return $this;
    }

    /**
     * Check for unique of identifier of block to selected store(s).
     *
     * @param AbstractModel $object
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsUniqueBlockToStores(AbstractModel $object)
    {
        $select = $this->getConnection()->select()
            ->from(['chsp' => $this->getMainTable()])            
            ->where('chsp.identifier = ?  ', $object->getData('identifier'));  

        if ($object->getId()) {
            $select->where('chsp.id != ?',$object->getId());
        }

        if ($this->getConnection()->fetchRow($select)  ) {
            return false;
        }

        return true;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     */
    public function _afterSave(AbstractModel $object)
    {
        $checkRequest = $this->request->getPostValue();
        if (isset($checkRequest['identifier'])) {
            $this->saveSecondaryData($this->request->getPostValue(), $object);
        }
    }

    public function saveSecondaryData($formData, $object)
    {
        if (isset($formData['data']['ibo_heroslider_form']['dynamic_rows_slider_information'])) {
            $this->saveRows($formData['data']['ibo_heroslider_form']['dynamic_rows_slider_information'], $object);
        }
    }

    /**
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function saveRows($dynamicRows, $object)
    {
        foreach ($dynamicRows as $key) {
            $jsonData = "";
            $jsonData = $this->serializer->unserialize($key['json']);
            $secondaryData = [];
            $secondaryData['title'] = $jsonData['title'];
            $secondaryData['banner_name'] = $jsonData['banner_name'];
            $secondaryData['label'] = $jsonData['label'];            
            $secondaryData['status'] = $key['status_row'];
            $secondaryData['sort_order'] = $jsonData['sort_order'];
            $secondaryData['web_link'] = $jsonData['web_link'];
            $secondaryData['app_link'] = $jsonData['app_link'];
            
            //For small image
            if (isset($jsonData['small_image'])) {
                $secondaryData['small_image'] = $jsonData['small_image']['name'];
            }
           
            //For large Image
            if (isset($jsonData['large_image'])) {
                $secondaryData['large_image'] = $jsonData['large_image']['name'];
            }
            
            $secondaryData['slider_id'] = $object->getId();
            if (!empty($key['id'])) {
                $secondaryData['id'] = $key['id'];
                $this->secondaryModel->setDataChanges(true);
                $this->secondaryResourceModel->load($this->secondaryModel, $secondaryData['id']);
            }
            $this->secondaryModel->setData($secondaryData);
            $this->secondaryResourceModel->save($this->secondaryModel);
        }
    }
}
