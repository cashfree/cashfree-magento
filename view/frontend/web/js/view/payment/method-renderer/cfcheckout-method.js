define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Customer/js/customer-data',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Cashfree_Cfcheckout/js/form/form-builder',
        'Magento_Ui/js/modal/alert',
        'Magento_Checkout/js/action/set-payment-information',
    ],
    function(Component, quote, $, additionalValidators, customerData, customer,placeOrderAction, fullScreenLoader, formBuilder, alert, setPaymentInformationAction){
    'use strict';

    return Component.extend({
        defaults:{
            'template':'Cashfree_Cfcheckout/payment/cfcheckout'
        },

        preparePayment: function (context, event) {

            if(!additionalValidators.validate()) {   //Resolve checkout aggreement accept error
                return false;
            }

            var serviceUrl,
                email,
                form;

            if (!customer.isLoggedIn()) {
                email = quote.guestEmail;
            } else {
                email = customer.customerData.email;
            }
            serviceUrl = window.checkoutConfig.payment.cfcheckout.redirectUrl+'?email='+email;

            this.isPaymentProcessing = $.Deferred();

            $.when(this.isPaymentProcessing).done(
                function () {
                    self.placeOrder();
                }
            ).fail(
                function (result) {
                    self.handleError(result);
                }
            );
            
            $.ajax({
                url: serviceUrl,
                type: 'post',
                context: this,
                data: {isAjax: 1},
                dataType: 'json',
                success: function (response) {
                    if ($.type(response) === 'object' && !$.isEmptyObject(response)) {
                        $('#cfcheckout_payment_form').remove();
                        form = formBuilder.build(
                            {
                                action: response.url,
                                fields: response.fields
                            }
                        );
                        customerData.invalidate(['cart']);
                        form.submit();
                    } else {
                        fullScreenLoader.stopLoader();
                        alert({
                            content: $.mage.__('Sorry, something went wrong. Please try again.')
                        });
                    }
                },
                error: function (response) {
                    fullScreenLoader.stopLoader();
                    alert({
                        content: $.mage.__('Sorry, something went wrong. Please try again later.')
                    });
                }
            });

            return;
        },

    });
});
