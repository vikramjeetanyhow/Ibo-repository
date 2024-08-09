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
            var max_capacity = uiRegistry.get('index = max_capacity');
            if ((value == '3')) {
                max_capacity.hide();
            } else {
                max_capacity.show();
            }
            return this._super();
        },
        fieldDepend: function (value){
            setTimeout( function(){
                var max_capacity = uiRegistry.get('index = max_capacity');
                if ((value == '3')) {
                    max_capacity.hide();
                } else {
                    max_capacity.show();
                }
            });
        }
    });
});