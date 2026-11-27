define([
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function (alert, $t) {
    'use strict';

    return {
        load: function (name, req, onload, config) {
            var checkoutConfig, paymentConfig, baseUrl;

            if (config.isBuild) {
                onload();
                return;
            }

            checkoutConfig = window.checkoutConfig;
            paymentConfig = checkoutConfig && checkoutConfig.payment &&
                checkoutConfig.payment.payfabric_payment;
            baseUrl = paymentConfig && paymentConfig.assetBaseUrl;

            if (!baseUrl) {
                alert({content: $t('Unable to load the PayFabric payment form.')});
                onload.error(new Error('PayFabric asset URL is not configured.'));
                return;
            }

            requirejs.config({
                paths: {
                    axios: baseUrl + '/Payment/WebGate/Content/scripts/lib/axios.min',
                    iframeResizer: baseUrl + '/Payment/WebGate/Content/scripts/lib/iframeResizer.min',
                    payfabricpayments: baseUrl +
                        '/Payment/WebGate/Content/bundles/payfabricpayments.standalone.min'
                }
            });

            req([
                'PayFabric_Payment/js/payfabric-dependencies',
                'PayFabric_Payment/js/payfabric-payments'
            ], function (dependencies, payfabricpayments) {
                onload({
                    axios: dependencies.axios,
                    iframeResizer: dependencies.iframeResizer,
                    payfabricpayments: payfabricpayments
                });
            }, function (error) {
                alert({content: $t('Unable to load the PayFabric payment form.')});
                onload.error(error);
            });
        }
    };
});
