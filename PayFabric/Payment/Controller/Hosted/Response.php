<?php

namespace PayFabric\Payment\Controller\Hosted;


use PayFabric\Payment\Helper\Helper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Response extends Action implements CsrfAwareActionInterface
{

    protected $resultPageFactory;

    protected $invoiceService;

    /**
     * Registry
     *
     * @var \Magento\Framework\Registry\Registry
     */
    private $registry;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;

    /**
     * @var \PayFabric\Payment\Helper\Helper
     */
    private $_helper;

    /**
     * Constructor
     *
     * @param  \Magento\Framework\App\Action\Context  $context
     * @param  \Magento\Framework\View\Result\PageFactory  $resultPageFactory
     * @param  \Magento\Framework\Registry  $registry
     * @param  Helper  $helper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Registry $registry,
        Helper $helper,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction
    ) {
        parent::__construct($context);
        $this->registry = $registry;
        $this->invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_helper = $helper;
    }

    /**
     * @param  RequestInterface  $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param  RequestInterface  $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Execute view action: LandingPageOnReturnAfterRedirect(sychronous)
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $request = $objectManager->get('\Magento\Framework\App\Request\Http');
        $urlInterface = $objectManager->get('\Magento\Framework\UrlInterface');

        try {
            $result = $this->_helper->executeGatewayTransaction("GET_STATUS", array('TrxKey' => $request->getParam('TrxKey')));
        } catch (\Exception $e) {
            $this->_redirect($urlInterface->getUrl('checkout/onepage/failure/'));
            return;
        }
        $orderId = isset($result->TrxUserDefine1) ? $result->TrxUserDefine1 : $request->getParam('orderid');
        $orders = $objectManager->get('Magento\Sales\Model\Order');
        $order = $orders->loadByIncrementId($orderId);

        $status = strtolower($result->TrxResponse->Status);
        if ($status == "approved") {
            $transactionState = strtolower($result->TransactionState);
            if($transactionState == "pending capture") { //Auth transaction
                if($order->getState() == 'pending_payment'){
                    return false;
                }
                $order->setState('pending_payment')
                    ->setStatus("pending_payment")
                    ->addStatusHistoryComment(__('Order payment authorized'))
                    ->setIsCustomerNotified(true);
                $order->save();

                $payment = $order->getPayment();
                $payment->setIsTransactionClosed(false);
                $payment->resetTransactionAdditionalInfo()
                    ->setTransactionId($request->getParam('TrxKey'));
                $transaction = $payment->addTransaction(Transaction::TYPE_AUTH, null, true);
                $transaction->setIsClosed(0);
                $transaction->save();
                $payment->save();
                $redirectUrl = $urlInterface->getUrl('checkout/onepage/success/');
            } elseif (in_array($transactionState,array('pending settlement','settled','captured'))){//Purchase transaction
                if($order->getStatus() != \Magento\Sales\Model\Order::STATE_PROCESSING && $order->getStatus() != \Magento\Sales\Model\Order::STATE_COMPLETE){
                    if($order->getState() == 'processing'){
                        return false;
                    }
//                    $order->setState("processing")
//                        ->setStatus("processing")
//                        ->addStatusHistoryComment(__('Payment completed successfully.'))
//                        ->setIsCustomerNotified(true);
//                    $order->save();

                    $payment = $order->getPayment();
                    $payment->setIsTransactionClosed(false);
                    $payment->resetTransactionAdditionalInfo()
                        ->setTransactionId($request->getParam('TrxKey'));
                    $payment->save();
                    try {
                        $this->_helper->generateInvoice($order, $this->invoiceService, $this->_transaction);
                        $transaction = $payment->addTransaction(Transaction::TYPE_ORDER, null, true);
                        $transaction->setIsClosed(0);
                        $transaction->save();
                    } catch (\Exception $e) {
                        //log
                    }
                }
                $redirectUrl = $urlInterface->getUrl('checkout/onepage/success/');
            } else {
                $redirectUrl = $urlInterface->getUrl('checkout/onepage/failure/');
            }
        } else {
            $order->setState("canceled")
                ->setStatus("canceled")
                ->addStatusHistoryComment('Order cancelled due to failed transaction: ' . $params['transactionKey'] . '(Order ID:' . $params['merchantTxId'] . ')' )->setIsCustomerNotified(true);
            $order->save();
            $redirectUrl = $urlInterface->getUrl('checkout/onepage/failure/');
        }
        $params['redirectUrl'] = $redirectUrl;
        $this->registry->register(\PayFabric\Payment\Block\Response::REGISTRY_PARAMS_KEY, $params);

        $this->_view->loadLayout();
        $this->_view->getLayout()->initMessages();
        $this->_view->renderLayout();
    }


}
