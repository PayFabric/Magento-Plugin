define([
    'axios',
    'iframeResizer'
], function (axios, iframeResizer) {
    'use strict';

    if (!axios || typeof iframeResizer !== 'function') {
        throw new Error('PayFabric payment dependencies failed to load.');
    }

    window.axios = axios;
    window.iFrameResize = iframeResizer;

    return {
        axios: axios,
        iframeResizer: iframeResizer
    };
});
