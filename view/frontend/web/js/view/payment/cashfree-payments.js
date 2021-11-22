define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],function(Component,renderList){
    'use strict';
    renderList.push({
        type : 'cashfree',
        component : 'Cashfree_Cfcheckout/js/view/payment/method-renderer/cashfree-method'
    });

    return Component.extend({});
})
