/**
 * @version [product Version 1.1.0] [Supported Magento Version 2.3.x.x]
 * @category Anyhow Infosystems
 * @package Magento Supermax POS
 * @author [Anyhow Infosystems] <[<https://anyhowinfo.com/>]>
 * @copyright Copyright (c) 2020 Anyhow Infosystems (OPC) Private Limited (https://anyhowinfo.com)
 * @license https://store.anyhowinfo.com/software-license
 */

var config = {
    map: {
        '*': {
            'Magento_Ui/js/form/element/image-uploader': 'Anyhow_SupermaxPos/js/form/element/image-uploader'
        }
    }
};

require([
    "jquery",
    'Magento_Ui/js/modal/alert',
    "jquery/ui",
    ], function($){
    $('#ah_supermax_pos_configuration_ah_supermax_pos_default_shipping_address_configuraion_ah_supermax_pos_country').on('change', function() 
    {
        var t = $("#ah_supermax_pos_configuration_ah_supermax_pos_default_shipping_address_configuraion_ah_supermax_pos_country").val();
         $('optgroup[label='+ t + ']').hide();
        console.log(t);
        //console.log(s);
        $('#ah_supermax_pos_configuration_ah_supermax_pos_default_shipping_address_configuraion_ah_supermax_pos_state_province optgroup[value="' + t +'"]').prop('selected', true);
    });
   });

//    require([
//     "jquery",
//     'Magento_Ui/js/form/components/button',
//     "jquery/ui",
//     ], function($){
//         $(document).ready(function () {
//             $(document).on('change','.pincodbtn',function(){
//                 var postcode = $("input[name=pincode]").val();
//                 $.ajax({
//                     type: 'GET',
//                     dataType:"json",
//                     contentType: "application/json",
//                     url: 'https://services.ibo.com/logistics/v1/post-codes/'+ postcode,
//                     headers:{         
//                         'trace_id' : '123',
//                         'client_id' : '123',
//                         'Content-Type':'application/json'
//                     },
//                     success: function (data) {
//                         console.log(data);
//                         $("input[name=city]").val(data.city);
//                         $("input[name=districtname]").val(data.city);
//                         $("input[name=statename]").val(data.state);
//                     },
//                     error: function (e) {
//                       console.log('error: ', e);
//                     }
//                 });
//             });
//         });
//     });