<?php

namespace PayFabric\Payment\Controller;

abstract class Checkout extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $_quoteRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote;

    /**
     * @var \PayFabric\Payment\Model\PaymentMethod
     */
    protected $_paymentMethod;

    /**
     * @var \PayFabric\Payment\Helper\Helper
     */
    protected $_checkoutHelper;

    /**
     * @var \Magento\Quote\Api\CartManagementInterface
     */
    protected $_cartManagement;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    protected $_resultJsonFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \PayFabric\Payment\Model\PaymentMethod $paymentMethod
     * @param \PayFabric\Payment\Helper\Helper $checkoutHelper
     * @param \Magento\Quote\Api\CartManagementInterface $cartManagement
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Psr\Log\LoggerInterface $logger,
        \PayFabric\Payment\Model\PaymentMethod $paymentMethod,
        \PayFabric\Payment\Helper\Helper $checkoutHelper,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_quoteRepository = $quoteRepository;
        $this->_orderFactory = $orderFactory;
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutHelper = $checkoutHelper;
        $this->_cartManagement = $cartManagement;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_logger = $logger;
        parent::__construct($context);
    }

    /**
     * Instantiate quote and checkout
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function initCheckout()
    {
        $quote = $this->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t initialize checkout.'));
        }
    }

    /**
     * Get order object
     *
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        return $this->_orderFactory->create()->loadByIncrementId(
            $this->_checkoutSession->getLastRealOrderId()
        );
    }

	/**
	 * @return \Magento\Quote\Model\Quote
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
    protected function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getCheckoutSession()->getQuote();
        }
        return $this->_quote;
    }

	/**
	 * @return \Magento\Checkout\Model\Session
	 */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

	/**
	 * @return \Magento\Customer\Model\Session
	 */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

	/**
	 * @return \PayFabric\Payment\Model\PaymentMethod
	 */
    public function getPaymentMethod()
    {
        return $this->_paymentMethod;
    }

	/**
	 * @return \PayFabric\Payment\Helper\Helper
	 */
    protected function getCheckoutHelper()
    {
        return $this->_checkoutHelper;
    }
}
