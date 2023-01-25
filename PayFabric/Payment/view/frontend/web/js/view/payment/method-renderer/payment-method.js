/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
        'Magento_Ui/js/modal/alert',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/set-billing-address',
        'PayFabric_Payment/js/action/set-payment-method',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/error-processor',
        'axios',
        'iframeResizer',
        'payfabricpayments'
    ],
    function(ko, $, alert, Component, setBillingAddressAction, setPaymentMethodAction, quote,
             additionalValidators, fullScreenLoader, errorProcessor, axios, iframeResizer, payfabricpayments) {this.axios = axios;this.iframeResizer = iframeResizer;this.payfabricpayments = payfabricpayments;
        'use strict';
        var paymentMethod = ko.observable(null);
        var timer = false;
        return Component.extend({
            paymentTrx: '',
            defaults: {
                template: 'PayFabric_Payment/payment/payment-form'
            },
            initialize: function() {
                this._super();
                if(window.checkoutConfig.payment['payfabric_payment'].isInPlace) {
                    this.initIframe();
                }
            },
            initIframe: function() {
                console.log('initIframe');
                var self = this;
                fullScreenLoader.startLoader();
                $.ajax({
                    url: window.checkoutConfig.payment['payfabric_payment'].redirectUrl,
                    type: 'post',
                    data: {isAjax: 1,email: quote.guestEmail},
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === "ok") {
                            if (typeof response.result.option === "undefined") {
                                $.mage.redirect(response.result);
                            }else{
                                new payfabricpayments($.extend(response.result.option, {
                                    successCallback: function (data) {
                                    },
                                    failureCallback: function (data) {
                                        fullScreenLoader.stopLoader();
                                        setTimeout(function(){location.reload();}, 3000);
                                    },
                                    cancelCallback: function () {
                                        location.reload();
                                    }
                                }));
                                self.setPaymentTrx(response.result.paymentTrx);
                            }
                        } else if(response.status === "error"){
                            alert({
                                content: response.message
                            });
                        }
                    },
                    error: function (response, data) {
                        alert({
                            content: $.mage.__('An error occurred. Try again!')
                        });
                    },
                    complete: function (response) {
                        console.log('complete initIframe');
                        fullScreenLoader.stopLoader();
                    }
                });

            },
            /** Redirect mode*/
            continueToPayment: function(data, event) {
                timer != false && clearTimeout( timer);
                event.preventDefault();
                event.stopPropagation();
                fullScreenLoader.startLoader();
                if (this.validate() && additionalValidators.validate()) {
                    var self = this;
                    var billingAddress = quote.billingAddress();
                    setBillingAddressAction().done(function() {
                        if (window.checkoutConfig.payment['payfabric_payment'].isInPlace) {
                            //If a in_place mode, needs to update with latest transaction
                            $.ajax({
                                url: window.checkoutConfig.payment['payfabric_payment'].redirectUrl,
                                type: 'post',
                                data: {isAjax: 1, email: quote.guestEmail, action: 'update', paymentTrx: self.getPaymentTrx()},
                                dataType: 'json',
                                success: function (response) {
                                    if (response.status === "ok") {
                                        var message = {
                                            action: "pay",
                                            BillCountryCode: billingAddress.countryId,
                                            BillAddressLine1: billingAddress.street[0],
                                            BillAddressLine2: billingAddress.street[1],
                                            BillCityCode: billingAddress.city,
                                            BillStateCode: billingAddress.regionCode,
                                            BillZipCode: billingAddress.postcode,
                                        };
                                        if(typeof window.frames['payfabric-sdk-iframe'] !== "undefined") {
                                            window.frames['payfabric-sdk-iframe'].postMessage(JSON.stringify(message), '*');
                                            timer = setTimeout(function () {
                                                fullScreenLoader.stopLoader()
                                            }, 15000);
                                        }else{
                                            alert({
                                                content: $.mage.__('Something went wrong, please try to refresh the page.')
                                            });
                                            fullScreenLoader.stopLoader();
                                        }
                                    } else if(response.status === "error"){
                                        alert({
                                            content: response.message
                                        });
                                        fullScreenLoader.stopLoader();
                                    }
                                },
                                error: function (response, data) {
                                    alert({
                                        content: $.mage.__('Unable to update the transaction.')
                                    });
                                    fullScreenLoader.stopLoader();
                                },
                                complete: function (response) {
                                    console.log('complete continueToPayment.');
                                }
                            });

                        } else {
                            fullScreenLoader.stopLoader();
                            self.initIframe();
                        }
                    }).fail(
                        function(response) {
                            errorProcessor.process(response);
                            fullScreenLoader.stopLoader();
                        });

                    return false;
                } else {
                    fullScreenLoader.stopLoader();
                }
            },
            validate: function() {
                return true;
            },
            getPaymentTrx: function() {
                return this.paymentTrx;
            },
            setPaymentTrx: function(paymentTrx) {
                this.paymentTrx = paymentTrx;
            },
        });
    }
);
