define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],function(Component,renderList){
    'use strict';
    renderList.push({
        type : 'cfcheckout',
        component : 'Cashfree_Cfcheckout/js/view/payment/method-renderer/cfcheckout-method'
    });

    return Component.extend({});
})
