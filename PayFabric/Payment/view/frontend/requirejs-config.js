/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

var config = {
    shim: {
        'payfabricpayments': {
            deps: ['PayFabric_Payment/js/payfabric-dependencies'],
            exports: 'payfabricpayments'
        }
    }
}