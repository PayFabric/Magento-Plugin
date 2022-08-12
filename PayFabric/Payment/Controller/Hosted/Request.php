<?php

namespace PayFabric\Payment\Controller\Hosted;

class Request extends \PayFabric\Payment\Controller\Checkout
{
    public function execute()
    {
        if ( ! $this->getRequest()->isAjax() ) {
            $this->getResponse()->setRedirect(
                $this->getCheckoutHelper()->getUrl( 'checkout' )
            )->sendResponse();
        }

        $quote         = $this->getQuote();
        $email         = $this->getRequest()->getParam( 'email' );
        $paymentMethod = $this->getPaymentMethod();
        //Update payment
        if ($this->getRequest()->getParam( 'action' ) == 'update' && $this->getRequest()->getParam( 'paymentTrx' )) {
            $result = $this->getCheckoutHelper()->updatePayment( $this->getRequest()->getParam( 'paymentTrx' ), $quote );
            die(json_encode($result));
        }

        $quote->setCustomerEmail( $email );
        $quote->reserveOrderId();
        if ( $this->getCustomerSession()->isLoggedIn() ) {
            $this->getCheckoutSession()->loadCustomerQuote();
            $quote->updateCustomerData( $this->getQuote()->getCustomer() );
            $quote->setCheckoutMethod( \Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER );
        } else {
            $quote->setCheckoutMethod( \Magento\Checkout\Model\Type\Onepage::METHOD_GUEST );
        }
        $this->_quoteRepository->save( $quote );
        $result = $this->getCheckoutHelper()->processPayment( $paymentMethod, $quote );
        die(json_encode($result));
    }
}
