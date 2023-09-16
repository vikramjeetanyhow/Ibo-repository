define([
    'Magento_Ui/js/modal/modal-component',
    'underscore',
    'uiRegistry',
    'ko',
    'jquery',
], function (modal,_,uiRegistry,ko,$) {
    'use strict';
    return modal.extend({
        /**
         * Initializes component.
         *
         * @returns {Object} Chainable.
         */
        initialize: function () {
            this._super();
            _.bindAll(this, 'initModal', 'openModal', 'closeModal', 'toggleModal', 'setPrevValues', 'validate');
            this.initializeContent();
            return this;
        },

        /**
         * Accept changes in modal by not preventing them.
         * Can be extended by exporting 'gatherValues' result somewhere
         */
        actionDone: function () {
            var self = this;
            this.valid = true;
            this.elems().forEach(this.validate, this);
            if (this.valid) {
                self.saveForm();
                this.closeModal();
            }
        },
        saveForm : function(){
            var self = this;
            var generalForm = uiRegistry.get('index = general');
            self.setImages();
            var saveForm = generalForm.source.data;
            var dynamicRows = uiRegistry.get('index = dynamic_rows_fieldset');
            dynamicRows = dynamicRows.source.data.data.ibo_heroslider_form.dynamic_rows_slider_information;
            this.formData = {
                'title':saveForm.title,
                'banner_name':saveForm.banner_name,
                'label':saveForm.label,                
                'sort_order':saveForm.sort_order,
                'web_link':saveForm.web_link,
                'app_link':saveForm.app_link
            };
            let positionField = uiRegistry.get('index=position');
            this.currentRow = positionField.value();
            let jsonField = uiRegistry.get('index = json');
            if (jsonField.value()!==""){
                if(dynamicRows[this.currentRow].json!==""){
                        self.editForm(dynamicRows[this.currentRow]);
                        self.showPreview(this.formData.title);
                }else {
                    let saveLast = dynamicRows[this.currentRow];
                    this.formData.record_id = this.currentRow;
                    this.formData.small_image = this.smallImage;
                    this.formData.large_image = this.largeImage;                    
                    self.showPreview(this.formData.title);
                    let newJSon = JSON.stringify(this.formData);
                    saveLast.json = newJSon;
                    self.resetFields();
                }
            }else {
                this.formData.record_id = this.currentRow;
                this.formData.small_image = this.smallImage;
                this.formData.large_image = this.largeImage;                
                self.showPreview(this.formData.title);
                let firstData = JSON.stringify(this.formData);
                jsonField.value(firstData);
                self.resetFields();
            }
            return true;
        },
        editForm:function(row){
            var self = this;
            let saveData = row;
            row = JSON.parse(row.json);
            this.formData.record_id = row.record_id;
            let smallImg = uiRegistry.get('index=small_image').value()[0];
            let largeImg = uiRegistry.get('index=large_image').value()[0];           
            this.formData.small_image = smallImg;
            this.formData.large_image = largeImg;            
            self.showPreview(this.formData.title);
            saveData.json = JSON.stringify(this.formData);
            self.resetFields();
        },
        resetFields:function(){
            let generalField = uiRegistry.get('index = general');
            generalField = generalField.source.data;
            for (let fields in generalField) {
                if (fields === 'title' || fields === 'banner_name' || fields === 'label' ||
                    fields === 'sort_order' || fields === 'web_link' || fields === 'app_link' || fields === 'small_image' || fields === 'large_image') {
                    let vals = uiRegistry.get('index=' + fields);
                    uiRegistry.get('index='+fields).validation["required-entry"] = false;
                    vals.value('');
                }
            }
        },
        setImages:function(){
            this.smallImage = uiRegistry.get('index=small_image').value()[0];
            this.largeImage = uiRegistry.get('index=large_image').value()[0];           
        },
        showPreview:function(title){
            this.title_row = uiRegistry.get
            ('inputName=data[ibo_heroslider_form][dynamic_rows_slider_information]['+this.currentRow+'][title_row]');
            this.title_row.value(title);
        }
    });
});
