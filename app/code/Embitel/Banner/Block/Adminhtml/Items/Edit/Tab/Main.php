<?php
/**
 * @category   Embitel
 * @package    Embitel_Banner
 * @author     hitendra.badiani@embitel.com
 */

namespace Embitel\Banner\Block\Adminhtml\Items\Edit\Tab;

use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Backend\Block\Widget\Tab\TabInterface;
use Embitel\Banner\Model\Group;
use Embitel\Banner\Model\ServiceCategory;
use Embitel\Banner\Model\Attributelist;

class Main extends Generic implements TabInterface
{
    protected $_wysiwygConfig;
    protected $groupList;
    protected $serviceCategory;
 
    public function __construct(
        \Magento\Backend\Block\Template\Context $context, 
        \Magento\Framework\Registry $registry, 
        \Magento\Framework\Data\FormFactory $formFactory,  
        \Magento\Cms\Model\Wysiwyg\Config $wysiwygConfig,
        Attributelist $attributelist, 
        Group $groupList, 
        ServiceCategory $serviceCategory, 
        array $data = []
    ) 
    {
        $this->attributelist = $attributelist;
        $this->groupList = $groupList;
        $this->serviceCategory = $serviceCategory;
        $this->_wysiwygConfig = $wysiwygConfig;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return __('Banner Information');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return __('Banner Information');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareForm()
    {
        $model = $this->_coreRegistry->registry('current_embitel_banner_items');
        /** @var \Magento\Framework\Data\Form $form */
        $form = $this->_formFactory->create();
        $form->setHtmlIdPrefix('item_');
        $htmlIdPrefix = $form->getHtmlIdPrefix();

        $bannerType = [['label' => 'Category', 'value' => '1'], ['label' => 'Product SKU', 'value' => '2']];
        
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => __('Item Information')]);
        if ($model->getId()) {
            $fieldset->addField('banner_id', 'hidden', ['name' => 'banner_id']);
            $catids_value = $model->getData('cat_ids');
        }else {
            $catids_value = NULL;
        }
        
        $fieldset->addField(
            'banner_type', 
            'select',
            array(
                'label' => __("Banner Type"),
                'class' => 'required-entry',
                'required' => 'true',
                'name' => 'banner_type',        
                'values' => $bannerType
            )
        );

        $fieldset->addField(
            'customer_group', 
            'multiselect',
            array(
                'label' => __("Select Customer Group"),               
                'class' => 'required-entry',
                'required' => 'true',
                'name' => 'customer_group',                        
                'values' => $this->groupList->toOptionArray()
            )
        ); 

        $fieldset->addField(
            'display_zone', 
            'select',
            array(
                'label' => __("Select Display Zone"),               
                'class' => 'required-entry',
                'required' => 'true',
                'name' => 'display_zone',                        
                'values' => $this->serviceCategory->toOptionArray()
            ),
            'banner_type',
        )->setAfterElementHtml("<script type=\"text/javascript\">
            require(['jquery', 'jquery/ui'],function($) {

                    // on intial check whether country code exit or not 
                        
                   $(window).on('load', function() {

                    var displayzone = $('#item_display_zone').val();
                    //var catids = $('#item_cat_ids').val();

                    var catids = '".$catids_value."';

                        //alert('catids '+catids+' displayzone '+displayzone);

                        $.ajax({
                               url : '".$this->getUrl('*/*/categorylist')."',
                               type: 'GET',
                               dataType: 'json',
                               showLoader:true,
                               data: {
                                    'form_key': window.FORM_KEY,
                                    'displayzone' :$('#item_display_zone').val(),
                                    'catids' :catids,
                               },                              
                               success: function(data){
                                    $('#item_cat_ids').empty();
                                    $('#item_cat_ids').append(data.htmlconent);
                               }
                            });

                    });   

                    // onchange displayzone this function called 

                   $(document).on('change', '#item_display_zone', function(event){

                    var displayzone = $('#item_display_zone').val();

                    //alert(displayzone);

                        $.ajax({
                               url : '". $this->getUrl('*/*/categorylist') . "displayzone/' + $('#item_display_zone').val(),
                               type: 'get',
                               dataType: 'json',
                               showLoader:true,
                               success: function(data){
                                    $('#item_cat_ids').empty();
                                    $('#item_cat_ids').append(data.htmlconent);
                               }
                            });
                   })
                }

            );
            </script>"
        );    

        $fieldset->addField(
            'title',
            'text',
            ['name' => 'title', 'label' => __('Banner Title'), 'title' => __('Title'), 'required' => true]
        ); 
          
        $fieldset->addField(
            'mobile_image',
            'image',
            [
                'name' => 'mobile_image',
                'label' => __('Mobile Image'),
                'title' => __('Mobile Image'),
                'class' => 'required-file',
                'required'  => false,
                'note' => 'Allow image type: jpg, jpeg, png'
            ]
        );

        $fieldset->addField(
            'desktop_image',
            'image',
            [
                'name' => 'desktop_image',
                'label' => __('Desktop Image'),
                'title' => __('Desktop Image'),
                'required'  => false,
                'note' => 'Allow image type: jpg, jpeg, png'
            ]
        );
        $fieldset->addField(
            'cat_ids',
            'multiselect',
            [
                'name' => 'cat_ids',
                'label' => __('Select Category'),
                'id' => 'cat_ids',
                'title' => __('Category'),
                'class' => 'required-entry',
                'required' => 'true',
                'values' =>[['value' => '', 'label' => '--Please Select Display Zone--']]
                
            ],
            'display_zone',
        );
        

        $fieldset->addField(
            'attribute_ids', 
            'multiselect',
            array(
                'label' => __("Select Filer Attribute"),
                'name' => 'attribute_ids',                
                'disabled' => true,           
                'values' => $this->attributelist->toOptionArray($model->getAttributeIds())
            ),
            'cat_ids',
        );

        $fieldset->addField(
            'products_sku',
            'file',
            [
                'name' => 'products_sku',
                'label' => __('Products SKU'),
                'title' => __('Products SKU'),
                'required'  => false,
                'display' => 'none',
                'note' => 'Allow file type: CSV'
            ],
            'banner_type',
        );

        $fieldset->addField(
            'banner_position',
            'text',
            ['name' => 'banner_position', 'label' => __('Banner Position'), 'class' => 'validate-number', 'title' => __('Banner Position'), 'required' => false]
        ); 

        $fieldset->addField(
            'from_date_time',
            'date',
            [
                'name' => 'from_date_time',
                'label' => __('Start Date'),
                'id' => 'from_date_time',
                'title' => __('Start Date'),
                'date_format' => 'yyyy-MM-dd',
                'time_format' => 'hh:mm:ss',
                'note' => 'From Date should be less than OR equal to today date.',
                'readonly' => true
            ]
        );

        $fieldset->addField(
            'to_date_time',
            'date',
            [
                'name' => 'to_date_time',
                'label' => __('End Date'),
                'id' => 'to_date_time',
                'title' => __('End Date'),
                'date_format' => 'yyyy-MM-dd',
                'time_format' => 'hh:mm:ss',
                'note' => 'End Date should be greater than OR equal to today date.',
                'readonly' => true
            ]
        );

        $fieldset->addField(
            'status',
            'select',
            ['name' => 'status', 'label' => __('Status'), 'title' => __('Status'),  'options'   => [0 => 'Disable', 1 => 'Enable'], 'required' => true]
        );                

       

        $this->setChild(
            'form_after',
            $this->getLayout()->createBlock(
                'Magento\Backend\Block\Widget\Form\Element\Dependence'
            )->addFieldMap(
                "{$htmlIdPrefix}banner_type",
                'banner_type'
            )
            ->addFieldMap(
                "{$htmlIdPrefix}cat_ids",
                'cat_ids'
            )
            ->addFieldMap(
                "{$htmlIdPrefix}products_sku",
                'products_sku'
            )
            ->addFieldMap(
                "{$htmlIdPrefix}attribute_ids",
                'attribute_ids'
            )
            ->addFieldDependence(
                'cat_ids',
                'banner_type',
                '1'
            )
             ->addFieldDependence(
                'products_sku',
                'banner_type',
                '2'
            )
            ->addFieldDependence(
                'attribute_ids',
                'banner_type',
                '1'
            )
        );

        $form->setValues($model->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }
}
