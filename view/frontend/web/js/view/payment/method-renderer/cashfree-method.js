define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/quote',
        'jquery',
        'ko',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'mage/url',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Ui/js/model/messageList',
        'Magento_Checkout/js/model/shipping-save-processor'
    ],
    function (Component, quote, $, ko, additionalValidators, setPaymentInformationAction, url, customer, placeOrderAction, fullScreenLoader, messageList) {
        'use strict';

        return Component.extend({
            defaults:{
                template:'Cashfree_Cfcheckout/payment/cfcheckout',
                cashfreeDataFrameLoaded: false,
                cf_response: {
                    transaction:{},
                    order:{}
                }
            },

            context: function() {
                return this;
            },

            isShowLegend: function() {
                return true;
            },

            getCode: function() {
                return 'cashfree';
            },

            getTitle: function() {
                return window.checkoutConfig.payment.cashfree.title;
            },

            isActive: function() {
                return true;
            },

            isAvailable: function() {
                return this.cashfreeDataFrameLoaded;
            },

            handleError: function (error) {
                alert(error);
                if (_.isObject(error)) {
                    this.messageContainer.addErrorMessage(error);
                } else {
                    this.messageContainer.addErrorMessage({
                        message: error
                    });
                }
            },

            initObservable: function() {
                var self = this._super();

                if(!self.cashfreeDataFrameLoaded) {

                    self.cashfreeDataFrameLoaded = true;
                }
                return self;
            },

            /**
            * @override
            */
            /** Process Payment */
            preparePayment: function (context, event) {
                if(!additionalValidators.validate()) {
                    return false;
                }

                fullScreenLoader.startLoader();
                this.placeOrder(event);
                return;
            },

            placeOrder: function (event) {
                var self = this;

                if (event) {
                    event.preventDefault();
                }

                if(!self.orderId) {
                    this.isPlaceOrderActionAllowed(false);
                    this.getPlaceOrderDeferredObject()
                        .fail(
                            function () {
                                self.isPlaceOrderActionAllowed(true);
                            }
                        ).done(
                        function (orderId) {
                            self.getCashfreeOrder(orderId);
                            self.orderId = orderId;
                        }
                    );
                }else{
                    self.getCashfreeOrder(self.orderId);
                }

                return;

            },

            getCashfreeOrder: function (orderId) {
                var self = this;
                self.isPaymentProcessing = $.Deferred();
                $.ajax({
                    type: 'POST',
                    url: url.build('cashfree/standard/request'),

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        if (response.success) {
                            self.doCheckoutPayment(response);
                        } else {
                            self.isPaymentProcessing.reject(response.message);
                        }
                    },

                    /**
                     * Error callback
                     * @param {*} response
                     */
                    error: function (response) {
                        fullScreenLoader.stopLoader();
                        self.isPaymentProcessing.reject(response.message);
                        self.handleError(response.responseJSON.message);
                        self.isPlaceOrderActionAllowed(true);
                    }
                });
            },

            doCheckoutPayment: function(cfResponse){
                var self = this,
                billing_address;
                this.messageContainer.clear();

                this.amount = quote.totals()['base_grand_total'];
                billing_address = quote.billingAddress();

                this.user = {
                    name: billing_address.firstname + ' ' + billing_address.lastname,
                    contact: billing_address.telephone,
                };

                if (!customer.isLoggedIn()) {
                    this.user.email = quote.guestEmail;
                }
                else
                {
                    this.user.email = customer.customerData.email;
                }

                $.getScript('https://sdk.cashfree.com/js/v3/cashfree.js', function() {
                    // This function will be executed once the script is loaded and executed.
                    const cashfree = Cashfree({
                        mode: "sandbox",
                    });

                    cashfree.checkout({
                        paymentSessionId: cfResponse.payment_session_id,
                        redirectTarget: "_self",
                        platformName: "mg",
                    });
                });
                return;

            }
        });
});
