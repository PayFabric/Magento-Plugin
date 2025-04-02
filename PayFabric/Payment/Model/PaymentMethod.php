<?php

namespace PayFabric\Payment\Model;

use PayFabric\Payment\Helper\Helper;
use PayFabric\Payment\Model\Config\Source\NewOrderPaymentActions;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\ConfigInterface;
use Magento\Payment\Model\Method\Online\GatewayInterface;
use Magento\Sales\Model\Order\Payment\Transaction;


class PaymentMethod extends \Magento\Payment\Model\Method\AbstractMethod implements GatewayInterface
{
    const METHOD_CODE = 'payfabric_payment';
    const NOT_AVAILABLE = 'N/A';

    /**
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * Payment Method feature.
     *
     * @var bool
     */
    protected $_canAuthorize = false;

    /**
     * @var bool
     */
    protected $_canCapture = true;

    /**
     * @var bool
     */
    protected $_canCapturePartial = true;

    /**
     * @var bool
     */
    protected $_canCaptureOnce = true;

    /**
     * @var bool
     */
    protected $_canRefund = true;

    /**
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;

    /**
     * @var bool
     */
    protected $_isGateway = true;

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var bool
     */
    protected $_canUseInternal = false;

    /**
     * @var bool
     */
    protected $_canVoid = true;


    protected $_canCancelInvoice = true;

    /**
     * @var bool
     */
    protected $_canReviewPayment = false;
    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    protected $_productMetadata;
    /**
     * @var \Magento\Framework\Module\ResourceInterface
     */
    protected $_resourceInterface;

    /**
     * @var \PayFabric\Payment\Helper\Helper
     */
    private $_helper;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;
    /**
     * @var \Magento\Framework\UrlInterface
     */
    private $_urlBuilder;
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $_resolver;
    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $_customerRepository;

    /**
     * PaymentMethod constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                      $request
     * @param \Magento\Framework\UrlInterface                              $urlBuilder
     * @param Helper                                                       $helper
     * @param \Magento\Store\Model\StoreManagerInterface                   $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface                  $resolver
     * @param \Magento\Framework\Model\Context                             $context
     * @param \Magento\Framework\Registry                                  $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory            $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory                 $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                                 $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface           $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                         $logger
     * @param \Magento\Framework\App\ProductMetadataInterface              $productMetadata
     * @param \Magento\Framework\Module\ResourceInterface                  $resourceInterface
     * @param \Magento\Customer\Api\CustomerRepositoryInterface            $customerRepository
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null           $resourceCollection
     * @param array                                                        $data
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        Helper $helper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\Module\ResourceInterface $resourceInterface,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_urlBuilder = $urlBuilder;
        $this->_helper = $helper;
        $this->_storeManager = $storeManager;
        $this->_resolver = $resolver;
        $this->_request = $request;
        $this->_productMetadata = $productMetadata;
        $this->_resourceInterface = $resourceInterface;
        $this->_customerRepository = $customerRepository;
    }

    /**
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initialize($paymentAction, $stateObject)
    {
        /*
         * do not send order confirmation mail after order creation wait for
         * result confirmation
         */
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        $stateObject->setState(\Magento\Sales\Model\Order::STATE_NEW);
        $stateObject->setStatus($this->_helper->getConfigData('order_status'));
        $stateObject->setIsNotified(false);
    }

    /**
     * Retrieve payment method title
     *
     * @return string
     */
    public function getTitle()
    {
        $title_code = $this->getConfigData('title');
        return $title_code;
    }

    /**
     * Recaptcha validation URL.
     *
     * @return string
     */
    public function getRecaptchaUrl()
    {
        return $this->_urlBuilder->getUrl('payfabric/hosted/recaptcha');
    }

    /**
     * Checkout redirect URL.
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see \Magento\Quote\Model\Quote\Payment::getCheckoutRedirectUrl()
     *
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('payfabric/hosted/request');
    }

    /**
     * Post request to gateway and return response.
     *
     * @param DataObject      $request
     * @param ConfigInterface $config
     */
    public function postRequest(DataObject $request, ConfigInterface $config)
    {
        // Do nothing
        $this->_helper->logDebug('Gateway postRequest called');
    }

    public function toAPIOperation($paymentAction)
    {
        switch ($paymentAction) {
            case NewOrderPaymentActions::PAYMENT_ACTION_AUTH: {
                return "AUTH";
            }
            case NewOrderPaymentActions::PAYMENT_ACTION_SALE: {
                return "PURCHASE";
            }
            default: {
                return strtoupper($paymentAction);
            }
        }
    }

    /**
     * Capture payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if(!$payment->getParentTransactionId())    return $this;
        parent::capture($payment, $amount);
        $params = array(
            "amount" => $amount,
            "originalMerchantTxId" => $payment->getParentTransactionId()
        );
		$result = $this->_helper->executeGatewayTransaction("CAPTURE", $params, $this->getStore());
        if(strtolower($result->Status) == 'approved') {
            $payment->setTransactionId($result->TrxKey)
                ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, json_decode(json_encode($result),true));
//            $order = $payment->getOrder();
//            $order->setState("processing")
//                ->setStatus("processing")
//                ->addStatusHistoryComment(__('Payment captured'));
//            $order->save();
        } else {
            throw new \Magento\Framework\Validator\Exception(isset($result->Message) ? __($result->Message) : __( 'Capture error!' ));
        }

        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        $params = array(
            "amount" => $amount,
            "originalMerchantTxId" => $payment->getRefundTransactionId()
        );
        $result = $this->_helper->executeGatewayTransaction("REFUND", $params, $this->getStore());
        if(strtolower($result->Status) == 'approved') {
            $payment->setTransactionId($result->TrxKey)
                ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, json_decode(json_encode($result), true));
//            $order = $payment->getOrder();
//            $order->setState("processing")
//                ->setStatus("processing")
//                ->addStatusHistoryComment('Payment refunded amount ' . $amount);
//            $transaction = $payment->addTransaction(Transaction::TYPE_REFUND, null, true);
//            $transaction->setIsClosed(0);
//            $transaction->save();
//            $order->save();
        } else {
            throw new \Magento\Framework\Validator\Exception(isset($result->Message) ? __($result->Message) : __( 'Refund error!' ));
        }

        return $this;
    }

    /**
     * Cancel payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @return $this
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }

    /**
     * Void payment
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @deprecated 100.2.0
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        parent::void($payment);
        $params = array(
            "originalMerchantTxId" => $payment->getParentTransactionId()
        );
        $result = $this->_helper->executeGatewayTransaction("VOID", $params, $this->getStore());
        if(strtolower($result->Status) == 'approved') {
            $payment->setTransactionId($result->TrxKey)
                ->setTransactionAdditionalInfo(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, json_decode(json_encode($result), true));
            $order = $payment->getOrder();
            $order->setState("canceled")
                ->setStatus("canceled")
                ->addStatusHistoryComment(__('Payment voided'));
            $transaction = $payment->addTransaction(Transaction::TYPE_VOID, null, true);
            $transaction->setIsClosed(1);
            $transaction->save();
            $order->save();
        } else {
            throw new \Magento\Framework\Validator\Exception(isset($result->Message) ? __($result->Message) : __( 'Void error!' ));
        }

        return $this;
    }

    /**
     * Retrieve request object.
     *
     * @return \Magento\Framework\App\RequestInterface
     */
    protected function _getRequest()
    {
        return $this->_request;
    }

    public function getUrlBuilder()
    {
        return $this->_urlBuilder;
    }
}
