<?php

namespace PayFabric\Payment\Controller\Hosted;
use Magento\Framework\Controller\ResultFactory;

class Callback extends \PayFabric\Payment\Controller\Checkout
{

	/**
	 * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
    public function execute()
    {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        // Get params from response
	    $params = $this->getRequest()->getParams();
	    $paymentMethod = $this->getPaymentMethod();

        try {
            $transactionId = $params['TrxKey'];
            $result = $this->getCheckoutHelper()->executeGatewayTransaction("GET_STATUS", array('TrxKey' => $transactionId));
        } catch (\Exception $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $messageManager = $objectManager->get('Magento\Framework\Message\ManagerInterface');
            $messageManager->addErrorMessage('Something went wrong. Your payment was not successful. Please try again!');
            $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/cart');
            return $this->getResponse()->setRedirect($returnUrl);
        }

        // Get payment method code
        $code = $paymentMethod->getCode();
        $refNo = isset($result->TrxUserDefine1) ? $result->TrxUserDefine1 : $params['OrderId'];
        $order = $this->getCheckoutHelper()->getOrderByIncrementId($refNo, $transactionId);

	    $quoteId = $this->getQuote()->getId();
	    $quote = $quoteId ? $this->_quoteRepository->get($quoteId) : $this->getCheckoutHelper()->getQuoteByIncrementId($refNo);
	    if ($quote) {
		    $this->getCheckoutSession()->replaceQuote($quote);
	    }

	    if (!$order && !$quote) {
		    $this->messageManager->addExceptionMessage(new \Exception(__('Missing order and quote data!')),
			    __('Missing order and quote data!'));
	    }

	    if ($quote && !$order) {
		    if ($this->getCustomerSession()->isLoggedIn()) {
			    if (isset($params['email']) && !empty($params['email'])) {
				    $quote->setCustomerEmail($params['email']);
			    }
			    $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_CUSTOMER);
		    } else {
			    $quote->setCheckoutMethod(\Magento\Checkout\Model\Type\Onepage::METHOD_GUEST);
		    }

		    $quote->setPaymentMethod($code);
		    $quote->getPayment()->importData(['method' => $code]);
		    $this->_quoteRepository->save($quote);
	    }

	    if (!$order) {
		    try {
			    $this->_cartManagement->placeOrder(
				    $quote->getId(),
				    $quote->getPayment()
			    );
			    $order = $this->getOrder();
			    $order->addStatusHistoryComment(__('Order created when redirected from payment page.'));

		    } catch (\Exception $e) {
			    $this->messageManager->addExceptionMessage($e, __('We can\'t place the order.'));

		    }
	    } else {
		    // set the checkoutSession for the redirect
		    $this->getCheckoutSession()
		         ->setLastSuccessQuoteId($quote->getId())
		         ->setLastQuoteId($quote->getId())
		         ->setLastRealOrderId($order->getIncrementId())
		         ->setLastOrderId($order->getId())
		         ->setLastOrderStatus($order->getStatus());
	    }

	    if ($order) {
		    $order->setExtOrderId($transactionId);
		    $order->save();

		    $invoiceService = $this->getCheckoutHelper()->getInvoiceService();
		    $transaction =  $this->getCheckoutHelper()->getTransaction();

		    $this->postProcessing($order, $params, $invoiceService, $transaction, $result);
		    $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
	    }
	    $this->getResponse()->setRedirect($returnUrl);
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @param                            $response
     */
    public function postProcessing( \Magento\Sales\Model\Order $order, $response, $invoiceService, $transaction, $result) {
        try {
            $transactionId = ( isset( $response['TrxKey'] ) ) ? $response['TrxKey'] : $response['trxKey'];
            $transactionState = strtolower($result->TransactionState);
            if($transactionState == "pending capture") { //Auth transaction
                if($order->getState() == 'holded'){
                    return false;
                }
                $order->setState('holded')
                    ->setStatus("holded")
                    ->addStatusHistoryComment(__('Order payment authorized'))
                    ->setIsCustomerNotified(true);
                $order->save();

                $payment = $order->getPayment();
                $payment->setIsTransactionClosed(false)
                        ->setTransactionId($transactionId);
                $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(0) ->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, (array) $response);
                $transaction->save();
                $payment->save();
            } elseif (in_array($transactionState,array('pending settlement','settled','captured'))){
                if($order->getStatus() != \Magento\Sales\Model\Order::STATE_PROCESSING && $order->getStatus() != \Magento\Sales\Model\Order::STATE_COMPLETE){
                    if($order->getState() == 'processing'){
                        return false;
                    }
                    $order->setState("processing")
                        ->setStatus("processing")
                        ->addStatusHistoryComment(__('Payment completed successfully.'))
                        ->setIsCustomerNotified(true);
                    $order->save();

                    $payment = $order->getPayment();
                    $payment->setIsTransactionClosed(false)
                            ->setTransactionId($transactionId);
                    $payment->save();
                    try {
                        $this->getCheckoutHelper()->generateInvoice($order, $invoiceService, $transaction);
                        $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_ORDER, null, true);
                        $transaction->setIsClosed(0)->setAdditionalInformation(\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS, (array) $response);
                        $transaction->save();
                    } catch (\Exception $e) {
                        throw new \Exception( sprintf( 'Error Creating Invoice: "%s"', $e->getMessage() ) );
                    }
                }
            }
            $this->getCheckoutHelper()->getOrderRepo()->save( $order );

            if ( ! $order->getEmailSent() ) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $orderSender = $objectManager->get(\Magento\Sales\Model\Order\Email\Sender\OrderSender::class);
                $orderSender->send($order);
            }

            return $order;

        } catch ( Exception $e ) {
            throw new \Exception( sprintf( 'Error Callback: "%s"', $e->getMessage() ) );
        }
    }

}
