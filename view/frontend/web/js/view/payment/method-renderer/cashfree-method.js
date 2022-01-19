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
    function (Component, quote, $, ko, additionalValidators, setPaymentInformationAction, url, customer, placeOrderAction, fullScreenLoader, messageList, shippingSaveProcessor) {
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

            getAppId: function() {
                return window.checkoutConfig.payment.cashfree.app_id;
            },

            isShowLegend: function() {
                return true;
            },

            getCode: function() {
                return 'cashfree';
            },

            getTitle: function() {
                return 'Cashfree';
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

                var self = this,
                    billing_address

                fullScreenLoader.startLoader();
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

                self.getCfOrderToken();

                return;
            },
            getCfOrderToken: function () {
                var self = this;

                //update shipping and billing before order into quotes
                if(!quote.isVirtual()) {
                    shippingSaveProcessor.saveShippingInformation().success(
                        function (response) {
                            self.createCashfreeOrder();
                        }
                    ).fail(
                        function (response) {
                            fullScreenLoader.stopLoader();
                            self.isPaymentProcessing.reject(response.message);
                        }
                    );
                } else {
                    self.createCashfreeOrder();
                }

            },
            createCashfreeOrder: function(){
                var self = this;
                $.ajax({
                    type: 'POST',
                    url: url.build('cashfree/standard/request'),
                    data: {
                        email: this.user.email,
                        billing_address: JSON.stringify(quote.billingAddress())
                    },

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        if (response.success) {
                            self.renderIframe(response);
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

            renderIframe: function(data) {
                var self = this;
                
                const successCallback = function (data) {
                    self.cf_response = data;
                    self.handledSuccessCallback(data);
                }
                const failureCallback = function (data) {
                    fullScreenLoader.stopLoader();
                    if(data.order && data.order.errorText) {
                        self.isPaymentProcessing.reject(data.order.errorText);
                    } else {
                        self.isPaymentProcessing.reject("Not a valid Cashfree Payments.");
                    }
                }
                const dismissCallback = function () {
                    fullScreenLoader.stopLoader();
                    self.isPaymentProcessing.reject("Payment Closed");
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
                $.ajax({
                    type: 'POST',
                    url: url.build('cashfree/standard/response'),
                    data: data,

                    /**
                     * Success callback
                     * @param {Object} response
                     */
                    success: function (response) {
                        //fullScreenLoader.stopLoader();
                        if (response.success) {
                            if(response.order_id){
                                $(location).attr('href', 'onepage/success?' + Math.random().toString(36).substring(10));
                            }else{
                                setTimeout(function(){ self.handledSuccessCallback(data); }, 1500);
                            }
                        } else {
                            self.placeOrder(data);
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
            
            getData: function() {
                return {
                    "method": this.item.method,
                    "po_number": null,
                    "additional_data": {
                        cf_transaction_id: this.cf_response.transaction.transactionId,
                        cf_order_id: this.cf_response.transaction.orderId,
                        cf_transaction_amount: this.cf_response.transaction.transactionAmount,
                        cf_order_status:this.cf_response.order.status
                    }
                };
            }
        });
});
