/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
    paths: {
        "payfabric" : "https://www.payfabric.com/Payment/WebGate/Content/bundles/payfabricpayments.bundle",
        "axios": "https://www.payfabric.com/Payment/WebGate/Content/scripts/lib/axios.min"
    },
    shim: {
        "payfabric": {
            deps: ["axios"],
            exports: "payfabric"
        },
        "axios": {
            exports: "axios"
        }
    }
}