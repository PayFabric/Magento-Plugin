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
        'axios'
    ],
    function(ko, $, Component, setBillingAddressAction, setPaymentMethodAction, quote,
             additionalValidators, fullScreenLoader, errorProcessor, axios) {
        'use strict';
        var paymentMethod = ko.observable(null);
        window.axios = axios;
        return Component.extend({
            self: this,
            defaults: {
                template: 'PayFabric_Payment/payment/payment-form'
            },
            initialize: function() {
                this._super();
            },
            /** Redirect mode*/
            continueToPayment: function(data, event) {
                event.preventDefault();
                event.stopPropagation();
                fullScreenLoader.startLoader();
                if (this.validate() && additionalValidators.validate()) {
                    var self = this;
                    setBillingAddressAction()
                        .done(
                            function() {
                                $.ajax({
                                    url: window.checkoutConfig.payment[quote.paymentMethod().method].redirectUrl,
                                    type: 'post',
                                    data: {isAjax: 1,email: quote.guestEmail},
                                    dataType: 'json',
                                    success: function (response) {
                                        fullScreenLoader.stopLoader();
                                        if (response.status === "ok") {
                                            if (typeof response.result.session === "undefined") {
                                                $.mage.redirect(response.result);
                                            }else{
                                                var script = document.createElement('script');
                                                script.src = 'https://www.payfabric.com/Payment/WebGate/Content/bundles/payfabricpayments.bundle.js';
                                                script.async = false;
                                                document.getElementById("cashierDiv").append(script);
                                                script.onload = function () {
                                                    new payfabricpayments($.extend(response.result, {
                                                        successCallback: function (data) {
                                                        },
                                                        failureCallback: function (data) {
                                                            //alert('Payment has failed for: ' + JSON.stringify(data, Object.getOwnPropertyNames(data)));
                                                            setTimeout(function(){location.reload();}, 3000);

                                                        },
                                                        cancelCallback: function () {
                                                            location.reload();;
                                                        }
                                                    }));
                                                    fullScreenLoader.stopLoader();
                                                }
                                            }
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
                            }
                        ).fail(
                        function(response) {
                            errorProcessor.process(response);
                            fullScreenLoader.stopLoader();
                        }
                    );

                    return false;
                }
            },
            validate: function() {
                return true;
            }
        });
    }
);