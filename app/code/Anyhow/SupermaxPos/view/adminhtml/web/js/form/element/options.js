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
 
    'Magento_Ui/js/form/element/single-checkbox',
 
    'Magento_Ui/js/modal/modal',
 
    'ko'
 
 ], function (_, uiRegistry, select, modal, ko) {
    'use strict';

    return select.extend({
        initialize: function () {
            this._super();
            this.fieldDepend(this.value());
            return this;
        },
        onUpdate: function (value){
            var firstname = uiRegistry.get('index = firstname');
            var lastname = uiRegistry.get('index = lastname');
            var company = uiRegistry.get('index = company');
            var street = uiRegistry.get('index = street');
            var telephone = uiRegistry.get('index = telephone');
            var city = uiRegistry.get('index = city');
            var postcode = uiRegistry.get('index = postcode');
            var country = uiRegistry.get('index = country_id');
            var region = uiRegistry.get('index = region_id');
 
            if (value == 0) {
                firstname.hide();
                lastname.hide();
                company.hide();
                street.hide();
                telephone.hide();
                city.hide();
                postcode.hide();
                country.hide();
                region.hide();
            } else {
                firstname.show();
                lastname.show();
                company.show();
                street.show();
                telephone.show();
                city.show();
                postcode.show();
                country.show();
                region.show();
            }
            return this._super();
        },
        fieldDepend: function (value){
            setTimeout( function(){
                var firstname = uiRegistry.get('index = firstname');
                var lastname = uiRegistry.get('index = lastname');
                var company = uiRegistry.get('index = company');
                var street = uiRegistry.get('index = street');
                var telephone = uiRegistry.get('index = telephone');
                var city = uiRegistry.get('index = city');
                var postcode = uiRegistry.get('index = postcode');
                var country = uiRegistry.get('index = country_id');
                var region = uiRegistry.get('index = region_id');
 
                 if (value == 0) {
                    firstname.hide();
                    lastname.hide();
                    company.hide();
                    street.hide();
                    telephone.hide();
                    city.hide();
                    postcode.hide();
                    country.hide();
                    region.hide();
                 } else { 
                    firstname.show();
                    lastname.show();
                    company.show();
                    street.show();
                    telephone.show();
                    city.show();
                    postcode.show();
                    country.show();
                    region.show();
                }
            });
        }
    });
 });
 
 
   
//    define([
//         'underscore',
//         'uiRegistry',
//         'Magento_Ui/js/form/element/single-checkbox',
//         'Magento_Ui/js/modal/modal',
//         'ko'
//     ], function (_, uiRegistry, select, modal, ko) {
//         'use strict';
//         return select.extend({      
    
//             initialize: function (){
    
//                 var firstname = uiRegistry.get('index = firstname');
//                 var lastname = uiRegistry.get('index = lastname');
//                 var company = uiRegistry.get('index = company');
//                 var street = uiRegistry.get('index = street');
//                 var telephone = uiRegistry.get('index = telephone');
//                 var city = uiRegistry.get('index = city');
//                 var postcode = uiRegistry.get('index = postcode');
//                 var country = uiRegistry.get('index = country_id');
//                 var region = uiRegistry.get('index = region_id');
//                 var status = this._super().initialValue;    
//                 if (status == 1) {
//                     firstname.show();
//                     lastname.show();
//                     company.show();
//                     street.show();
//                     telephone.show();
//                     city.show();
//                     postcode.show();
//                     country.show();
//                     region.show();
//                 } else{
//                     firstname.hide();
//                     lastname.hide();
//                     company.hide();
//                     street.hide();
//                     telephone.hide();
//                     city.hide();
//                     postcode.hide();
//                     country.hide();
//                     region.hide();
//                 }
//                 return this;
    
//             },      
    
//             /**
//              * On value change handler.
//              *
//              * @param {String} value
//              */
//             onUpdate: function (value) {
    
//                 var firstname = uiRegistry.get('index = firstname');
//                 var lastname = uiRegistry.get('index = lastname');
//                 var company = uiRegistry.get('index = company');
//                 var street = uiRegistry.get('index = street');
//                 var telephone = uiRegistry.get('index = telephone');
//                 var city = uiRegistry.get('index = city');
//                 var postcode = uiRegistry.get('index = postcode');
//                 var country = uiRegistry.get('index = country_id');
//                 var region = uiRegistry.get('index = region_id');
//                 if (value == 1) {
//                     firstname.show();
//                     lastname.show();
//                     company.show();
//                     street.show();
//                     telephone.show();
//                     city.show();
//                     postcode.show();
//                     country.show();
//                     region.show();
//                 } else {
//                     firstname.hide();
//                     lastname.hide();
//                     company.hide();
//                     street.hide();
//                     telephone.hide();
//                     city.hide();
//                     postcode.hide();
//                     country.hide();
//                     region.hide();
//                 }           
//                 return this._super();
//             },
//         });
//     }); 