define([
    'payfabricpayments'
], function (PayFabricPayments) {
    'use strict';

    function Payments(options, nonce) {
        this.cspNonce = nonce || null;
        PayFabricPayments.call(this, options);
    }

    Payments.prototype = Object.create(PayFabricPayments.prototype);
    Payments.prototype.constructor = Payments;

    Payments.prototype.getScriptTag = function (method, handler) {
        var script = PayFabricPayments.prototype.getScriptTag.call(this, method, handler);
        var hostname = new URL(script.src, window.location.href).hostname;

        if (this.cspNonce && (hostname === 'paypal.com' || hostname.endsWith('.paypal.com'))) {
            script.setAttribute('nonce', this.cspNonce);
            script.setAttribute('data-csp-nonce', this.cspNonce);
        }

        return script;
    };

    return Payments;
});
