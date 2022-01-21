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
             additionalValidators, fullScreenLoader, errorProcessor, axios, iframeResizer, payfabricpayments) {this.axios = axios;this.iframeResizer = axios;this.payfabricpayments = payfabricpayments;
        'use strict';
        var paymentMethod = ko.observable(null);
        return Component.extend({
            self: this,
            defaults: {
                template: 'PayFabric_Payment/payment/payment-form'
            },
            initialize: function() {
                this._super();
            },
            initIframe: function() {
                console.log('initIframe');
                $.ajax({
                    url: window.checkoutConfig.payment['payfabric_payment'].redirectUrl,
                    type: 'post',
                    data: {isAjax: 1,email: quote.guestEmail},
                    dataType: 'json',
                    success: function (response) {
                        fullScreenLoader.stopLoader();
                        if (response.status === "ok") {
                            if (typeof response.result.session === "undefined") {
                                $.mage.redirect(response.result);
                            }else{
                                // var script = document.createElement('script');
                                // script.src = 'http://10.124.64.158/static/version1640673938/frontend/Magento/luma/en_US/PayFabric_Payment/js/payfabricpayments.bundle.js';
                                // script.async = false;
                                // document.getElementById("payment_form_"+quote.paymentMethod().method).append(script);
                                // script.onload = function () {
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
                                fullScreenLoader.stopLoader();
                            }
                            // }
                        } else if(response.status === "error"){
                            alert(response.message);
                        }
                    },
                    error: function (response, data) {
                        alert('An error occurred. Try again!');
                    },
                    complete: function (response) {

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
                    self.initIframe();

                    return false;
                }
            },
            validate: function() {
                return true;
            }
        });
    }
);