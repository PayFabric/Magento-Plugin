<?php

namespace PayFabric\Payment\Model\ConfigProvider;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\ObjectManager;
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
        \PayFabric\Payment\Helper\Helper $helper
    )
    {
        $this->_paymentHelper = $paymentHelper;
        $this->_helper = $helper;
        $this->cspNonceProvider = null;

        $cspNonceProviderClass = 'Magento\Csp\Helper\CspNonceProvider';
        if (class_exists($cspNonceProviderClass)) {
            $this->cspNonceProvider = ObjectManager::getInstance()->get($cspNonceProviderClass);
        }

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
                $config['payment'] [$code]['cspNonce'] = $this->cspNonceProvider
                    ? $this->cspNonceProvider->generateNonce()
                    : null;
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
