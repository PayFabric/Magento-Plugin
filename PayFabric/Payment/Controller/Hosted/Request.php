<?php

namespace PayFabric\Payment\Controller\Hosted;

use PayFabric\Payment\Helper\Helper;
use PayFabric\Payment\Model\PaymentMethod;
use Symfony\Component\Config\Definition\Exception\Exception;
class Request extends \PayFabric\Payment\Controller\Checkout
{
    public function execute()
    {
        if ( ! $this->getRequest()->isAjax() ) {
            $this->_cancelPayment();
            $this->_checkoutSession->restoreQuote();
            $this->getResponse()->setRedirect(
                $this->getCheckoutHelper()->getUrl( 'checkout' )
            );
        }

        $quote         = $this->getQuote();
        $email         = $this->getRequest()->getParam( 'email' );
        $paymentMethod = $this->getPaymentMethod();
        $quote->setCustomerEmail( $email );
        $quote->reserveOrderId();
        if ( $this->getCustomerSession()->isLoggedIn() ) {
            $this->getCheckoutSession()->loadCustomerQuote();
            $quote->updateCustomerData( $this->getQuote()->getCustomer() );
            $quote->setCheckoutMethod( \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER );
        } else {
            $quote->setCheckoutMethod( \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST );
        }

        $quote->setPaymentMethod( $this->getPaymentMethod()->getCode() );
        $quote->getPayment()->importData( [ 'method' => $this->getPaymentMethod()->getCode() ] );
        $this->_quoteRepository->save( $quote );
        $result = $this->getCheckoutHelper()->processPayment( $paymentMethod, $quote );
        die(json_encode($result));
    }

    public function getFormFields()
    {
        $result = [];
        try {
            if ($this->_order->getPayment()) {
                $result = $this->_order->getPayment()->getMethodInstance()->getFormFields();
            }
        } catch (Exception $e) {
            $this->_helper->logDebug('Could not get redirect form fields: '.$e);
        }

        return $result;
    }

    public function getMerchantLandingPageUrl(){
        $result = '';
        try {
            $order = $this->_order;
            if ($order->getPayment()) {
                $result = $this->_order->getPayment()->getMethodInstance()->getMerchantLandingPageUrl();
            }
        } catch (Exception $e) {
            $this->_helper->logDebug('Could not get MerchantLandingPageUrl: '.$e);
            throw($e);
        }

        return $result;
    }

    /**
     * Get order object.
     *
     * @return \Magento\Sales\Model\Order
     */
    private function _getOrder()
    {
        if (!$this->_order) {
            $incrementId = $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }

        return $this->_order;
    }

    /**
     * Get frontend checkout session object.
     *
     * @return \Magento\Checkout\Model\Session
     */
    private function _getCheckout()
    {
        return $this->_checkoutSession;
    }
}
