define([
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/form/components/button',
    'Magento_Ui/js/modal/modal',
    'ko',
    'jquery',
    'mage/url',
], function (_, uiRegistry, button, modal, ko, $,
             url) {
    'use strict';
    return button.extend({
        initialize: function () {
            this._super();
            return this;
        },
        action: function () {
            var self = this;
            var dynamicRows = uiRegistry.get('index = dynamic_rows_fieldset');
            var lastRow = dynamicRows.source.data.data.ibo_heroslider_form.dynamic_rows_slider_information;
            this.selectedRow = this.parentName;
            this.selectedRow = this.selectedRow.charAt(this.selectedRow.length-1);
            self.setPosition(this.selectedRow);
            let jsonField = uiRegistry.get('index = json');
            this.title = uiRegistry.get('index=title');
            this.banner_name = uiRegistry.get('index=banner_name');
            this.label = uiRegistry.get('index=label');            
            this.web_link  = uiRegistry.get('index=web_link');            
            this.app_link  = uiRegistry.get('index=app_link');            
            this.sort_order = uiRegistry.get('index=sort_order');           
            this.largeImage = uiRegistry.get('index=large_image');
            this.smallImage = uiRegistry.get('index=small_image');           
            if (jsonField.value()!=="") {
                let row = jsonField.value();
                self.fillForms(lastRow[this.selectedRow]);
            }
            var mymodal = uiRegistry.get('index = popup_modal');
            self.requiredFieldsActivate();
            mymodal.openModal();
        },
        fillForms:function(row){
            if (row.json!=="") {
                let parseJson = JSON.parse(row.json);
                this.title.value(parseJson.title);
                this.banner_name.value(parseJson.banner_name);
                this.label.value(parseJson.label);                
                this.web_link.value(parseJson.web_link);                
                this.app_link.value(parseJson.app_link);                
                this.sort_order.value(parseJson.sort_order);                
                this.smallImage.value([parseJson.small_image]);
                this.largeImage.value([parseJson.large_image]);
            }else{
                this.resetFields();
            }
        },
        resetFields:function(){
            let generalField = uiRegistry.get('index = general');
            generalField = generalField.source.data;
            for (let fields in generalField) {
                if (fields === 'title' || fields === 'banner_name' || fields === 'label' ||
                    fields === 'sort_order' || fields === 'web_link' || fields === 'app_link' || fields === 'small_image' || fields === 'large_image'
                    ) {
                    let vals = uiRegistry.get('index=' + fields);
                    vals.value('');
                }
            }
        },
        setPosition:function(pos){
            let positionField = uiRegistry.get('index=position');
            positionField.value(pos);
        },
        requiredFieldsActivate:function(){
            let generalField = uiRegistry.get('index = general');
            generalField = generalField.source.data;
            for (let fields in generalField) {
                if (fields === 'small_image' || fields === 'large_image') {
                    uiRegistry.get('index='+fields).validation["required-entry"] = true;
                }
            }
        }
    })
});
