/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

define([

    'underscore',
 
    'uiRegistry',
 
    'Magento_Ui/js/form/element/select',
 
    'Magento_Ui/js/modal/modal'
 ], function (_, uiRegistry, select, modal) {
    'use strict';

    return select.extend({
        initialize: function () {
            this._super();
            this.fieldDepend(this.value());
            return this;
        },
        onUpdate: function (value){
            var ezetap_app_key = uiRegistry.get('index = ezetap_app_key');
            var ezetap_username = uiRegistry.get('index = ezetap_username');
            var ezetap_device_id = uiRegistry.get('index = ezetap_device_id');
            var pinelabs_device_id = uiRegistry.get('index = pinelabs_device_id');
            var pinelabs_allowed_mops = uiRegistry.get('index = pinelabs_allowed_mops');
            var pinelabs_merchant_pos_code = uiRegistry.get('index = pinelabs_merchant_pos_code');
 
            if ((value == 'pinelabs-axis') || (value == 'pinelabs-icici')) {
                ezetap_app_key.hide();
                ezetap_username.hide();
                ezetap_device_id.hide();
                pinelabs_device_id.show();
                pinelabs_allowed_mops.show();
                pinelabs_merchant_pos_code.show();
            } else {
                ezetap_app_key.show();
                ezetap_username.show();
                ezetap_device_id.show();
                pinelabs_device_id.hide();
                pinelabs_allowed_mops.hide();
                pinelabs_merchant_pos_code.hide();
            }
            return this._super();
        },
        fieldDepend: function (value){
            setTimeout( function(){
                var ezetap_app_key = uiRegistry.get('index = ezetap_app_key');
                var ezetap_username = uiRegistry.get('index = ezetap_username');
                var ezetap_device_id = uiRegistry.get('index = ezetap_device_id');
                var pinelabs_device_id = uiRegistry.get('index = pinelabs_device_id');
                var pinelabs_allowed_mops = uiRegistry.get('index = pinelabs_allowed_mops');
                var pinelabs_merchant_pos_code = uiRegistry.get('index = pinelabs_merchant_pos_code');
 
                if ((value == 'pinelabs-axis') || (value == 'pinelabs-icici')) {
                    ezetap_app_key.hide();
                    ezetap_username.hide();
                    ezetap_device_id.hide();
                    pinelabs_device_id.show();
                    pinelabs_allowed_mops.show();
                    pinelabs_merchant_pos_code.show();
                } else {
                    ezetap_app_key.show();
                    ezetap_username.show();
                    ezetap_device_id.show();
                    pinelabs_device_id.hide();
                    pinelabs_allowed_mops.hide();
                    pinelabs_merchant_pos_code.hide();
                }
            });
        }
    });
});