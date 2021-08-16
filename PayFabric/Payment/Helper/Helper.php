<?php

namespace PayFabric\Payment\Helper;

use PayFabric\Payment\Helper\sdk\lib\Configurable;
use PayFabric\Payment\Helper\sdk\lib\Payments;
use PayFabric\Payment\Model\Config\Source\DisplayMode;
use Magento\Framework\App\Helper\AbstractHelper;
use PayFabric\Payment\Model\Config\Source\Environment;
/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Helper extends AbstractHelper
{
    const METHOD_CODE = 'payfabric_payment';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;
    private $_encryptor;
    /**
     * parameters to initiate the SDK payment.
     *
     */
    protected $environment_params;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->_encryptor = $encryptor;
    }

    public static function log()
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/eservice.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $str = '';
        foreach (func_get_args() as $arg){
            $str .= print_r($arg,true);
        }
        $logger->info($str);
    }

    /**
     * @desc Returns true if integration is in sandbox mode
     *
     * @return bool
     */
    public function isSandboxMode()
    {
        return $this->getConfigData('environment') == Environment::ENVIRONMENT_SANDBOX;
    }

    public function getNotificationRoute($orderId)
    {
        return 'payfabric/hosted/callback/orderid/' . $orderId;
    }

    public function getLandingPageOnReturnAfterRedirect($orderId)
    {
        return 'payfabric/hosted/response/orderid/' . $orderId;
    }

    /**
     * @desc Get Cashier URL
     *
     * @return string
     */
    public function getCashierUrl()
    {
        $maxiPago = new Payments();
        $maxiPago->setEnvironment($this->isSandboxMode());
        return $maxiPago->cashierUrl;
    }
    /**
     * @desc Get Cashier JS API URL
     *
     * @return string
     */
    public function getJsUrl()
    {
        $maxiPago = new Payments();
        $maxiPago->setEnvironment($this->isSandboxMode());
        return $maxiPago->jsUrl;
    }
    /**
     * @desc Returns the method of the HTTP Request that the form will execute
     *
     * @return string
     */
    public function getFormMethod()
    {
        $displayMode = $this->getConfigData('display_mode');
        if ($displayMode === DisplayMode::DISPLAY_MODE_REDIRECT) {
            return "GET";
        } else if ($displayMode === DisplayMode::DISPLAY_MODE_HOSTEDPAY) {
            return "GET";
        }else if ($displayMode === DisplayMode::DISPLAY_MODE_EMBEDDED) {

        } else if ($displayMode === DisplayMode::DISPLAY_MODE_IFRAME) {
            return "POST";
        } else {
            $this->logDebug("Display mode not valid: " . $displayMode);
            return '';
        }
    }

    public function logDebug($message)
    {
        if ($this->getConfigData('debug_log') == '1') {
            $this->_logger->debug($message);
        }
    }

    /**
 * @desc Cancels the order
 *
 * @param \Magento\Sales\Mode\Order $order
 */
    public function cancelOrder($order)
    {
        $orderStatus = $this->getConfigData('payment_cancelled');
        $order->setActionFlag($orderStatus, true);
        $order->cancel()->save();
    }

    /**
     * @desc Sets the order to pending
     *
     * @param \Magento\Sales\Mode\Order $order
     */
    public function setPendingOrder($order)
    {
        $order->setState("pending_payment")->setStatus("pending_payment")->addStatusHistoryComment('Payment is pending')->setIsCustomerNotified(true);
        $order->save();
    }

    public function getConfigData($field, $storeId = null)
    {
        $fieldData = $this->getConfig($field, self::METHOD_CODE, $storeId);
        if ($field == 'merchant_password') {
            $fieldData = $this->_encryptor->decrypt($fieldData);
        }
        return $fieldData;
    }

    /**
     * @desc Gives back configuration values as flag
     *
     * @param $field
     * @param null $storeId
     *
     * @return mixed
     */
    public function getConfigDataFlag($field, $storeId = null)
    {
        return $this->getConfig($field, self::METHOD_CODE, $storeId, true);
    }

    /**
     * @desc Retrieve information from payment configuration
     *
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     *
     * @return bool|mixed
     */
    public function getConfig($field, $paymentMethodCode, $storeId, $flag = false)
    {
        $path = 'payment/'.$paymentMethodCode.'/'.$field;
        if (null === $storeId) {
            $storeId = $this->_storeManager->getStore();
        }

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    /**
     * Construct IPG payment gateway
     * @param Configurable $configurable
     * @param array $params
     * @return Configurable|Payments
     */
    public function constructIPG(Configurable $configurable = null, $params = array())
    {
        $baseParams = array();
        if ($this->isSandboxMode()) {
            $baseParams["tokenURL"] = $this->getConfigData('token_url_sandbox');
            $baseParams["cashierURL"] = $this->getConfigData('cashier_url_sandbox');
            $baseParams["paymentsURL"] = $this->getConfigData('payments_url_sandbox');
        } else {
            //return (new Payments())->productionEnvironment($params);
        }
        if (!isset($configurable)) {
            $configurable = new Payments($params);
        }
        $configurable->environmentUrls($baseParams);
        $baseParams =  array(
            "merchantId" => $this->getConfigData('merchant_id'),
            "password" => $this->getConfigData('merchant_password'),
            "timestamp" => time() * 1000,
            "channel" => 'ECOM',

        );
        foreach($baseParams as $key => $value) {
            call_user_func_array(array($configurable, $key), array($value));
        }
        /*foreach($params as $key => $value) {
            call_user_func_array(array($configurable, $key), array($value));
        }*/
		//throw new \Magento\Framework\Validator\Exception(__(json_encode($configurable->_data)));
        return $configurable;
    }

    public function setCommonParams($configurable)
    {
        $baseParams = array();
        if ($this->isSandboxMode()) {
            $baseParams["tokenURL"] = $this->getConfigData('token_url_sandbox');
            $baseParams["cashierURL"] = $this->getConfigData('cashier_url_sandbox');
            $baseParams["paymentsURL"] = $this->getConfigData('payments_url_sandbox');
        } else {
            $baseParams["tokenURL"] = $this->getConfigData('token_url_production');
            $baseParams["cashierURL"] = $this->getConfigData('cashier_url_production');
            $baseParams["paymentsURL"] = $this->getConfigData('payments_url_production');
        }

        $configurable->environmentUrls($baseParams);
        $baseParams =  array(
            "merchantId" => $this->getConfigData('merchant_id'),
            "password" => $this->getConfigData('merchant_password'),
            "timestamp" => time() * 1000,
            "channel" => 'ECOM',

        );
        foreach($baseParams as $key => $value) {
            call_user_func_array(array($configurable, $key), array($value));
        }

        //throw new \Magento\Framework\Validator\Exception(__(json_encode($configurable->_data)));
        return $configurable;
    }

    public function executeGatewayTransaction($action, $params = array()) {
        if(!$this->getConfigData('merchant_id') || !$this->getConfigData('merchant_password')){
            throw new \Magento\Framework\Exception\LocalizedException(__('miss merchant configuration info'));
        }

        $maxiPago = new Payments();
        $maxiPago->setLogger(PayFabric_LOG_DIR,PayFabric_LOG_SEVERITY);
        $maxiPago->setCredentials($this->getConfigData('merchant_id') , $this->getConfigData('merchant_password'));
        $maxiPago->setDebug(PayFabric_DEBUG);
        $maxiPago->setEnvironment($this->getConfigData('environment'));

        switch ($action){
            case "TOKEN":
                $maxiPago->token($params);
                if(empty(json_decode($maxiPago->response)->Token)){
                    return  $maxiPago->response;
                }
                break;
            case "AUTH":
                $maxiPago->creditCardAuth($params);
                $responseTran = json_decode($maxiPago->response);
                if(empty($responseTran->Key)){
                    return  $maxiPago->response;
                }
                return $this->executeGatewayTransaction("TOKEN", array("Audience" => "PaymentPage" , "Subject" => $responseTran->Key));
            case "PURCHASE":
                $maxiPago->creditCardSale($params);
                $responseTran = json_decode($maxiPago->response);
                if(empty($responseTran->Key)){
                    return  $maxiPago->response;
                }
                return $this->executeGatewayTransaction("TOKEN", array("Audience" => "PaymentPage" , "Subject" => $responseTran->Key));
            case "CAPTURE":
                $maxiPago->creditCardCapture($params['originalMerchantTxId']);
                break;
            case "REFUND":
                $maxiPago->creditCardRefund(array(
                    'Amount'=>$params['amount'],
                    'ReferenceKey'=>$params['originalMerchantTxId']
                ));
                break;
            case "VOID":
                $maxiPago->creditCardVoid($params['originalMerchantTxId']);
                break;
            case "GET_STATUS":
                $maxiPago->retrieveTransaction($params['TrxKey']);
                break;
        }
        return json_decode($maxiPago->response);
    }

    public function generateInvoice($order, $invoiceService, $transaction){
        try {
            if (!$order->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('The order no longer exists.'));
            }
            if(!$order->canInvoice()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The order does not allow an invoice to be created.')
                );
            }

            $invoice = $invoiceService->prepareInvoice($order);
            if (!$invoice) {
                throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t save the invoice right now.'));
            }
            if (!$invoice->getTotalQty()) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You can\'t create an invoice without products.')
                );
            }
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->save();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
//            $order->addStatusHistoryComment(__('Automatically INVOICED'), true);
            $transactionSave = $transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__(json_encode($e->getMessage())));
        }

        return $invoice;
    }
}
