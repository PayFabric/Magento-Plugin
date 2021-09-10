<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayFabric\Payment\Model\Config\Backend;

use PayFabric\Payment\Helper\sdk\lib\Payments;
use PayFabric\Payment\Model\Config\Source\NewOrderPaymentActions;

/**
 * @api
 * @since 100.0.2
 */
class Gateway extends \Magento\Framework\App\Config\Value
{
    private $_encryptor;
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->_storeManager = $storeManager;
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        if($this->getFieldsetDataValue('active')){
            $api_merchant_id = $this->getFieldsetDataValue('merchant_id');
            $api_password = $this->getFieldsetDataValue('merchant_password');
            $sandbox = $this->getFieldsetDataValue('environment');
            $payment_action = $this->getFieldsetDataValue('payment_action');
            $maxiPago = new Payments();
            $maxiPago->setLogger(PayFabric_LOG_DIR,PayFabric_LOG_SEVERITY);

            // Set your credentials before any other transaction methods
            $maxiPago->setCredentials($api_merchant_id, $api_password);

            $maxiPago->setDebug(PayFabric_DEBUG);
            $maxiPago->setEnvironment($sandbox);
            $data = array(
                'Amount' => '0.01',
                'Currency' => $this->_storeManager->getStore()->getBaseCurrencyCode()
            );
            $payment_action == NewOrderPaymentActions::PAYMENT_ACTION_AUTH ? $maxiPago->creditCardAuth($data) : $maxiPago->creditCardSale($data);

            $responseTran = json_decode($maxiPago->response);
            if(empty($responseTran->Key)){
                throw new \UnexpectedValueException($maxiPago->response, 503);
            }
        }
        parent::beforeSave();
        return $this;
    }
}
