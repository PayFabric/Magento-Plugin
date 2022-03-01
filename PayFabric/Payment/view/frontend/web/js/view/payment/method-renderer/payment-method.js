/*browser:true*/
/*global define*/
define(
    [
        'ko',
        'jquery',
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
    function(ko, $, Component, setBillingAddressAction, setPaymentMethodAction, quote,
             additionalValidators, fullScreenLoader, errorProcessor, axios, iframeResizer, payfabricpayments) {this.axios = axios;this.iframeResizer = iframeResizer;this.payfabricpayments = payfabricpayments;
        'use strict';
        var paymentMethod = ko.observable(null);
        return Component.extend({
            self: this,
            defaults: {
                template: 'PayFabric_Payment/payment/payment-form'
            },
            initialize: function() {
                this._super();
                if(window.checkoutConfig.payment['payfabric_payment'].displayMode == 'in_place') {
                    this.initIframe();
                }
            },
            initIframe: function() {
                console.log('initIframe');
                fullScreenLoader.startLoader();
                $.ajax({
                    url: window.checkoutConfig.payment['payfabric_payment'].redirectUrl,
                    type: 'post',
                    data: {isAjax: 1,email: quote.guestEmail},
                    dataType: 'json',
                    success: function (response) {
                        //fullScreenLoader.stopLoader();
                        if (response.status === "ok") {
                            if (typeof response.result.session === "undefined") {
                                $.mage.redirect(response.result);
                            }else{
                                new payfabricpayments($.extend(response.result, {
                                    successCallback: function (data) {
                                    },
                                    failureCallback: function (data) {
                                        //alert('Payment has failed for: ' + JSON.stringify(data, Object.getOwnPropertyNames(data)));
                                        setTimeout(function(){location.reload();}, 3000);

                                    },
                                    cancelCallback: function () {
                                        location.reload();
                                    }
                                }));
                                if(window.checkoutConfig.payment['payfabric_payment'].displayMode == 'in_place') {
                                    setInterval(function () {
                                        window.frames['payfabric-sdk-iframe'].postMessage(JSON.stringify({action: "hide"}), '*');
                                    }, 1000);
                                }
                                setTimeout(function(){fullScreenLoader.stopLoader();}, 3000);
                            }
                        } else if(response.status === "error"){
                            alert(response.message);
                        }
                    },
                    error: function (response, data) {
                        alert('An error occurred. Try again!');
                    },
                    complete: function (response) {
                        console.log('complete initIframe');
                    }
                });

            },
            /** Redirect mode*/
            continueToPayment: function(data, event) {
                event.preventDefault();
                event.stopPropagation();
                fullScreenLoader.startLoader();
                if (this.validate() && additionalValidators.validate()) {
                    var self = this;
                    var billingAddress = quote.billingAddress();
                    setBillingAddressAction().done(function() {
                        if (window.checkoutConfig.payment['payfabric_payment'].displayMode == 'in_place') {
                            //If a in_place mode, needs to update with latest transaction
                            $.ajax({
                                url: window.checkoutConfig.payment['payfabric_payment'].redirectUrl,
                                type: 'post',
                                data: {isAjax: 1, email: quote.guestEmail, action: 'update'},
                                dataType: 'json',
                                success: function (response) {
                                    var message = {
                                        action: "pay",
                                        BillCountryCode:billingAddress.countryId,
                                        BillAddressLine1:billingAddress.street[0],
                                        BillAddressLine2:billingAddress.street[1],
                                        BillCityCode:billingAddress.city,
                                        BillStateCode:billingAddress.regionCode,
                                        BillZipCode:billingAddress.postcode,
                                    };
                                    window.frames['payfabric-sdk-iframe'].postMessage(JSON.stringify(message), '*');
                                },
                                error: function (response, data) {
                                    alert('An update error occurred. Try again!');
                                },
                                complete: function (response) {
                                    console.log('complete!');
                                }
                            });

                        } else {
                            self.initIframe();
                        }
                    }).fail(
                        function(response) {
                            errorProcessor.process(response);
                            fullScreenLoader.stopLoader();
                        });

                    return false;
                }
            },
            validate: function() {
                return true;
            }
        });
    }
);