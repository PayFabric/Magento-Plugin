/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
    paths: {
        "axios": "https://www.payfabric.com/Payment/WebGate/Content/scripts/lib/axios.min",
        "payfabricpayments": "https://www.payfabric.com/Payment/WebGate/src/output/payfabricpayments",
        "iframeResizer":"https://www.payfabric.com/Payment/WebGate/Content/scripts/lib/iframeResizer.min"
    },
    shim: {
        'payfabricpayments': {
            deps: ["iframeResizer", "axios"],
            exports: 'payfabricpayments'
        }
    }
}