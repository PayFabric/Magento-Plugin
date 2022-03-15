<?php

namespace PayFabric\Payment\Controller\Hosted;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Callback extends \PayFabric\Payment\Controller\Checkout implements CsrfAwareActionInterface
{

	/**
	 * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
	 * @throws \Magento\Framework\Exception\LocalizedException
	 * @throws \Magento\Framework\Exception\NoSuchEntityException
	 */
    public function execute()
    {
        $returnUrl = $this->getCheckoutHelper()->getUrl('checkout');

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            // Get callback
            $params = $this->getRequest()->getParams();
        } else if ($raw_post = file_get_contents('php://input')) {
            //Post callback
            $parts = parse_url($raw_post);
            parse_str($parts['path'], $params);
            return $this->postCallback($params);
        }
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
        $refNo = isset($result->TrxUserDefine1) ? $result->TrxUserDefine1 : '';
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

	    if (!$order = $this->getCheckoutHelper()->getOrderByIncrementId($refNo, $transactionId)) {
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
            $returnUrl = $this->getCheckoutHelper()->getUrl('checkout/onepage/success');
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
                if($order->getState() == 'processing'){
                    return false;
                }
                $order->setState('processing')
                    ->setStatus("processing")
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

    public function postCallback($params){
        sleep(3);
        $paymentMethod = $this->getPaymentMethod();
        try {
            $transactionId = $params['TrxKey'];
            $result = $this->getCheckoutHelper()->executeGatewayTransaction("GET_STATUS", array('TrxKey' => $transactionId));
        } catch (\Exception $e) {
            throw new \Exception( sprintf( 'Error post callback to get the result: "%s"', $e->getMessage() ) );
        }
        // Get payment method code
        $code = $paymentMethod->getCode();
        $refNo = isset($result->TrxUserDefine1) ? $result->TrxUserDefine1 : '';
        $order = $this->getCheckoutHelper()->getOrderByIncrementId($refNo, $transactionId);
        $quote = $this->getCheckoutHelper()->getQuoteByIncrementId($refNo);

        if (!$order && !$quote) {
            die(json_encode(__('Missing order and quote data!')));
        }

        if ($quote && !$order) {
            $quote->setPaymentMethod($code);
            $quote->getPayment()->importData(['method' => $code]);
            $this->_quoteRepository->save($quote);
        }

        if (!$order = $this->getCheckoutHelper()->getOrderByIncrementId($refNo, $transactionId)) {
            try {
                $this->_cartManagement->placeOrder(
                    $quote->getId(),
                    $quote->getPayment()
                );
                $order = $this->getOrder();
                $order->addStatusHistoryComment(__('Order created by post callback.'));
                $order->setExtOrderId($transactionId);
                $order->save();

                $invoiceService = $this->getCheckoutHelper()->getInvoiceService();
                $transaction =  $this->getCheckoutHelper()->getTransaction();
                $this->postProcessing($order, $params, $invoiceService, $transaction, $result);

            } catch (\Exception $e) {
                throw new \Exception( sprintf( 'Error post callback to place the order: "%s"', $e->getMessage() ) );
            }
        }
        die(json_encode(true));
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
