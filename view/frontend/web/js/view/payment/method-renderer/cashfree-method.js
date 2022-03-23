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

                self.getInContext = window.checkoutConfig.payment.cashfree.in_context;
                if(self.getInContext === false) {
                    $.mage.redirect(cfResponse.payment_link);
                }else{
                    self.renderDropin(cfResponse);

                    this.isPaymentProcessing = $.Deferred();

                    $.when(this.isPaymentProcessing).fail(
                        function (result) {
                            self.handleError(result);
                        }
                    );
                }
                return;

            },

            renderDropin: async function(data) {
                var self = this;
                
                const successCallback = function (data) {
                    self.cf_response = data;
                    self.orderStatus = data.order.status;
                    self.handledSuccessCallback(data);
                }
                const failureCallback = function (data) {
                    fullScreenLoader.stopLoader();
                    if(data.order && data.order.errorText) {
                        self.isPaymentProcessing.reject(data.order.errorText);
                    } else {
                        self.isPaymentProcessing.reject("Your transaction has been failed.");
                    }
                }
                const dismissCallback = function (data) {
                    if (self.orderStatus != 'PAID') {
                        self.handleCart(data);
                        fullScreenLoader.stopLoader();
                        self.isPaymentProcessing.reject("Payment Closed");
                        self.isPlaceOrderActionAllowed(true);
                    }
                }
                let orderToken = "";
                let env = data.environment;
                orderToken = data.order_token;
                if (orderToken == "") {
                    fullScreenLoader.stopLoader();
                    self.isPaymentProcessing.reject("Order token is not generated.");
                }
                Pippin(env, orderToken, successCallback, failureCallback, dismissCallback);
                
            },
            handledSuccessCallback: function (data) {
                var self = this;
                if(self.successCalled){
                    return;
                }
                self.successCalled = true;
                fullScreenLoader.startLoader();
                var postData = {
                    "method": self.item.method,
                    "po_number": null,
                    "additional_data": {
                        cf_transaction_id: data.transaction.transactionId,
                        cf_order_id: data.order.orderId,
                        cf_transaction_amount: data.transaction.transactionAmount,
                        cf_order_status:data.order.status
                    }
                }
                $.ajax({
                    type: 'POST',
                    url: url.build('cashfree/standard/response'),
                    data: postData,

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        require('Magento_Customer/js/customer-data').reload(['cart']);

                        if (!response.success) {
                            fullScreenLoader.stopLoader();
                            self.isPaymentProcessing.reject(response.message);
                            self.handleError(response);
                            self.isPlaceOrderActionAllowed(true);
                            self.successCalled = false;
                        }

                        window.location.replace(url.build(response.redirect_url));
                    },

                    /**
                     * Error callback
                     * @param {*} response
                     */
                    error: function (response) {
                        fullScreenLoader.stopLoader();
                        if(response.responseJSON && response.responseJSON.message) {
                            self.isPaymentProcessing.reject(response.responseJSON.message);
                        } else {
                            self.isPaymentProcessing.reject("Not a valid Cashfree Payments.");
                        }
                        self.isPlaceOrderActionAllowed(true);
                    }
                });
            },

            handleCart: function(data){
                var self = this;
                fullScreenLoader.startLoader();

                $.ajax({
                    type: 'POST',
                    url: url.build('cashfree/standard/handleCart'),
                    data: JSON.stringify(data),
                    dataType: 'json',
                    contentType: 'application/json',

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        self.isPaymentProcessing.reject('order_failed');
                        require('Magento_Customer/js/customer-data').reload(['cart']);

                        if (response.success) {
                            window.location.replace(url.build(response.redirect_url));
                        }
                    },

                    /**
                     * Error callback
                     * @param {*} response
                     */
                    error: function (response) {
                        fullScreenLoader.stopLoader();
                        self.isPaymentProcessing.reject(response.message);
                        self.handleError(response);
                    }
                });

            }
        });
});
