<?php
/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

namespace Anyhow\SupermaxPos\Block\Adminhtml\Product\Edit;

use Magento\Framework\View\Asset\Repository; 

class Form extends \Magento\Backend\Block\Widget\Form\Generic
{
    protected $_assetRepo;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        array $data = []
    ) {   
        parent::__construct($context, $registry, $formFactory, $data);
    }


    protected function _prepareForm()
    {   
        $path = $this->_assetRepo->getUrl("Anyhow_SupermaxPos::sample/Anyhow_SupermaxPos_Sample_File.csv");
        $model = $this->_coreRegistry->registry('row_data');
        $form = $this->_formFactory->create(
            ['data' => [
                    'id' => 'edit_form',
                    'enctype' => 'multipart/form-data',
                    'action' => $this->getData('action'),
                    'method' => 'post'
                ]
            ]
        );
        $form->setHtmlIdPrefix('datalocation_');
        $fieldset = $form->addFieldset(
            'base_fieldset',
            ['legend' => __('Import Barcode '), 'class' => 'fieldset-wide']
        );
        
        $importdata_script =  $fieldset->addField(
            'importdata',
            'file', 
            array(
                'label'     => 'Upload File',
                'required'  => true,
                'name'      => 'importdata',  
                'note' => 'Allow File type: .csv', 
            )
        );

        $importdata_script->setAfterElementHtml("   
            <span id='sample-file-span' ><a id='sample-file-link' href='".$path."'  >Download Sample File</a></span>   
            <script type=\"text/javascript\">
                document.getElementById('supermax_importdata').onchange = function () { 
                    var fileInput = document.getElementById('supermax_importdata');
                    var filePath = fileInput.value;
                    var allowedExtensions = /(\.csv|\.xls)$/i; 
                    if(!allowedExtensions.exec(filePath))
                    {
                        alert('Please upload file having extensions .csv only.');
                        fileInput.value = '';
                    }
                };
            </script>"
        );
        //$form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }
}