<?php

namespace PayFabric\Payment\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Payment\Helper\Data as PaymentHelper;

class PayFabricConfigProvider implements ConfigProviderInterface
{

    private $_paymentHelper;
    private $_helper;
    private $cspNonceProvider;
    protected $_methodCodes = [
        'payfabric_payment',
    ];

    private $methods = [];

    public function __construct(
        PaymentHelper $paymentHelper,
        \PayFabric\Payment\Helper\Helper $helper,
        CspNonceProvider $cspNonceProvider
    )
    {
        $this->_paymentHelper = $paymentHelper;
        $this->_helper = $helper;
        $this->cspNonceProvider = $cspNonceProvider;

        foreach ($this->_methodCodes as $code) {
            $this->methods[$code] = $this->_paymentHelper->getMethodInstance($code);
        }
    }

    public function getConfig()
    {
        $config = [
            'payment' => [
                'payfabric_payment' => [
                ],
            ],
        ];

        foreach ($this->_methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment'] [$code]['redirectUrl'] = $this->getMethodRedirectUrl($code);
                $config['payment'] [$code]['isInPlace'] = $this->_helper->isInPlace('display_mode');
                $config['payment'] [$code]['recaptchaUrl'] = $this->getRecaptchaUrl($code);
                $config['payment'] [$code]['cspNonce'] = $this->cspNonceProvider->generateNonce();
                $config['payment'] [$code]['assetBaseUrl'] = $this->_helper->getAssetBaseUrl();
            }
        }

        return $config;
    }

    private function getMethodRedirectUrl($code)
    {
        return $this->methods[$code]->getCheckoutRedirectUrl();
    }

    private function getRecaptchaUrl($code)
    {
        return $this->methods[$code]->getRecaptchaUrl();
    }
}
