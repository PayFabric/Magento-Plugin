<?php

namespace PayFabric\Payment\Helper;

use PayFabric\Payment\Helper\sdk\lib\Payments;
use PayFabric\Payment\Model\Config\Source\DisplayMode;
use Magento\Framework\App\Helper\AbstractHelper;
use PayFabric\Payment\Model\Config\Source\Environment;

class Helper extends AbstractHelper
{
    const METHOD_CODE = 'payfabric_payment';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $_storeManager;
    private $_encryptor;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Quote\Model\QuoteRepository $quoteRepo,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepo
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->_encryptor = $encryptor;
        $this->_orderFactory = $orderFactory;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_quoteRepo             = $quoteRepo;
        $this->_invoiceService        = $invoiceService;
        $this->_transaction    = $transaction;
        $this->_transactionBuilder    = $transactionBuilder;
        $this->_orderRepo          = $orderRepo;
    }

    /**
     * @desc Return true if sandbox mode
     *
     * @return bool
     */
    public function isSandboxMode()
    {
        return $this->getConfigData('environment') == Environment::ENVIRONMENT_SANDBOX;
    }

    /**
     * @desc Return asynchronous notification url as an alternative
     *
     * @return string
     */
    public function getNotificationRoute($orderId)
    {
        return 'payfabric/hosted/callback/OrderId/' . $orderId;
    }

    /**
     * @desc Return synchronous notification url
     *
     * @return string
     */
    public function getLandingPageOnReturnAfterRedirect($orderId)
    {
        return 'payfabric/hosted/response/orderid/' . $orderId;
    }

    /**
     * @desc Return Cashier URL
     *
     * @return string
     */
    public function getCashierUrl()
    {
        $maxiPago = new Payments();
        $maxiPago->setEnvironment($this->getConfigData('environment'));
        return $maxiPago->cashierUrl;
    }

    /**
     * @desc Return Cashier JS API URL
     *
     * @return string
     */
    public function getJsUrl()
    {
        $maxiPago = new Payments();
        $maxiPago->setEnvironment($this->getConfigData('environment'));
        return $maxiPago->jsUrl;
    }

    /**
     * @desc Return the method of the HTTP Request that the form executes
     *
     * @return string
     */
    public function getFormMethod()
    {
        $displayMode = $this->getConfigData('display_mode');
        if ($displayMode === DisplayMode::DISPLAY_MODE_REDIRECT) {
            return "GET";
        } else if ($displayMode === DisplayMode::DISPLAY_MODE_IFRAME) {
            return "GET";
        } else {
            $this->logDebug("Display mode not valid: " . $displayMode);
            return '';
        }
    }

    /**
     * @desc Log runtime debug log in var/log/debug.log
     *
     * @return string
     */
    public function logDebug($message)
    {
        if ($this->getConfigData('debug_log') == '1') {
            $this->_logger->debug($message);
        }
    }

    /**
     * @desc Read the values in etc/config.xml
     *
     * @param $field
     * @param $storeId
     *
     * @return bool|mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        $fieldData = $this->getConfig($field, self::METHOD_CODE, $storeId);
        if ($field == 'merchant_password') {
            $fieldData = $this->_encryptor->decrypt($fieldData);
        }
        return $fieldData;
    }

    /**
     * @desc If it's a yes/no flag and you want to get a true/false value you can do it like this:
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
     * @desc Magento\Framework\App\Config implements \Magento\Framework\App\Config\ScopeConfigInterface
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
     * @desc Execute the gateway transaction
     *
     * @param $action
     * @param $params
     *
     * @return string|object
     */
    public function executeGatewayTransaction($action, $params = array())
    {
        try {
            $maxiPago = new Payments();
            $maxiPago->setLogger(PayFabric_LOG_DIR,PayFabric_LOG_SEVERITY);
            $maxiPago->setCredentials($this->getConfigData('merchant_id') , $this->getConfigData('merchant_password'));
            $maxiPago->setDebug(PayFabric_DEBUG);
            $maxiPago->setEnvironment($this->getConfigData('environment'));

            switch ($action){
                case "TOKEN":
                    $maxiPago->token($params);
                    if(empty(json_decode($maxiPago->response)->Token)){
                        throw new \UnexpectedValueException($maxiPago->response, 503);
                    }
                    break;
                case "AUTH":
                    $maxiPago->creditCardAuth($params);
                    $responseTran = json_decode($maxiPago->response);
                    if(empty($responseTran->Key)){
                        throw new \UnexpectedValueException($maxiPago->response, 503);
                    }
                    return $this->executeGatewayTransaction("TOKEN", array("Audience" => "PaymentPage" , "Subject" => $responseTran->Key));
                case "PURCHASE":
                    $maxiPago->creditCardSale($params);
                    $responseTran = json_decode($maxiPago->response);
                    if(empty($responseTran->Key)){
                        throw new \UnexpectedValueException($maxiPago->response, 503);
                    }
                    return $this->executeGatewayTransaction("TOKEN", array("Audience" => "PaymentPage" , "Subject" => $responseTran->Key));
                case "CAPTURE":
                    $maxiPago->creditCardCapture(array(
                        'Amount'=>$params['amount'],
                        'ReferenceKey'=>$params['originalMerchantTxId']
                    ));
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
        }catch (\Exception $e){
            throw $e;
        }
    }

    /**
     * @desc Generate invoice with capture
     *
     * @param $action
     * @param $params
     *
     * @return string|object
     */
    public function generateInvoice($order, $invoiceService, $transaction)
    {
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
            $order->addStatusHistoryComment(__('Automatically INVOICED'), true);
            $transactionSave = $transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Validator\Exception(__(json_encode($e->getMessage())));
        }

        return $invoice;
    }

    /**
     * @param $paymentMethod
     * @param $quote
     *
     * @return array
     * @throws Exception
     */
    public function processPayment( $paymentMethod, $quote ) {
        $billingAddress  = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();
        $billingArray    = $this->getBillingArray( $billingAddress );
        $shippingArray   = $this->getShippingArray( $shippingAddress, $billingAddress );

        // customer id
        $customerId = $quote->getCustomerId();
        if ($customerId == '') {
            $customerId = 'guest_'.$quote->getReservedOrderId();
        }
        if(strlen($customerId) > 20) {
            $customerId = substr($customerId, -20);
        }

        $url = $paymentMethod->getUrlBuilder()->getBaseUrl();
        $parse_result = parse_url($url);
        if(isset($parse_result['port'])){
            $allowOriginUrl = $parse_result['scheme']."://".$parse_result['host'].":".$parse_result['port'];
        }else{
            $allowOriginUrl = $parse_result['scheme']."://".$parse_result['host'];
        }

        $sessionTokenData = array_merge(array(
            "action" => $paymentMethod->toAPIOperation($this->getConfigData('payment_action')),
            "referenceNum" => sprintf( '%s', $quote->getReservedOrderId() ),
            "Amount" => $this->formatAmount($quote->getGrandTotal()),
            "Currency" => strtoupper( $quote->getCurrency()->getQuoteCurrencyCode() ),
            "pluginName" => "Magento PayFabric Gateway",
            "pluginVersion" => "1.1.0",
            "customerId" => $customerId,
            //level2/3
            'freightAmount'    => $this->formatAmount($shippingAddress->getBaseShippingAmount()),
            'taxAmount' => $this->formatAmount($shippingAddress->getBaseTaxAmount()),
            'lineItems' => $this->get_level3_data_from_order($quote),
            //Optional
            'allowOriginUrl' => $allowOriginUrl,
            "merchantNotificationUrl" => $this->getUrl($this->getNotificationRoute($quote->getReservedOrderId())),
        ), $shippingArray, $billingArray);

        try {
            $responseToken = $this->executeGatewayTransaction($sessionTokenData['action'], $sessionTokenData);
        }catch (\Exception $e){
            $this->_logger->critical( sprintf( 'There was an error in the api response, last known error was "%s", in file %s, at line %s. Error message: %s',
                json_last_error(), __FILE__, __LINE__, $e->getMessage() ) );
            return  array('status' => 'error', 'message' => $e->getMessage());
        }
        $displayMode = $paymentMethod->getConfigData('display_mode');
        switch ($displayMode) {
            case DisplayMode::DISPLAY_MODE_IFRAME:
                $displayMethod = 'dialog';
                $disableCancel = false;
                break;
            case DisplayMode::DISPLAY_MODE_IN_PLACE:
                $displayMethod = 'in_place';
                $disableCancel = true;
                break;
            default:
                $displayMethod = '';
                $disableCancel = false;
                break;
        }
        if ($displayMethod) {
            $result = array(
                'environment' => $this->isSandboxMode() ? (stripos(TESTGATEWAY,'DEV-US2')===FALSE ? (stripos(TESTGATEWAY,'QA')===FALSE ? 'SANDBOX' : 'QA') : 'DEV-US2') : 'LIVE',
                'target' => 'payment_form_'.self::METHOD_CODE,
                'displayMethod' => $displayMethod,
                'session' => $responseToken->Token,
                'disableCancel' => $disableCancel,
                'successUrl' => $this->getUrl($this->getNotificationRoute($quote->getReservedOrderId()))
            );
        } else {
            $result = $this->getCashierUrl() . "?" . http_build_query(array(
                'token' => $responseToken->Token,
                'successUrl' => $this->getUrl($this->getNotificationRoute($quote->getReservedOrderId()))
            ));
        }

        return array(
            'status' => 'ok',
            'result' => $result
        );

    }

    /**
     * @param $billingAddress
     *
     * @return array
     */
    public function getBillingArray( $billingAddress ) {
        $billingArr                = [
            'billingAddress1'    => substr( $billingAddress->getStreet()[0], 0, 40 ),
            'billingAddress2'    => ( ! empty( $billingAddress->getStreet()[1] ) ) ? substr( $billingAddress->getStreet()[1], 0, 40 ) : '',
            'billingAddress3'    => ( ! empty( $billingAddress->getStreet()[2] ) ) ? substr( $billingAddress->getStreet()[2], 0, 40 ) : '',
            'billingCity'         => substr( $billingAddress->getCity(), 0, 50 ),
            'billingCountry' => strtoupper( $billingAddress->getCountryId() ),
            'billingFirstName'   => substr( $billingAddress->getFirstname(), 0, 50 ),
            'billingLastName'    => substr( $billingAddress->getLastname(), 0, 50 ),
            'billingEmail' => substr( $billingAddress->getEmail(), 0, 250 ),
            'billingPhone'        => preg_replace( '/[^0-9]+/', '', substr( $billingAddress->getTelephone(), 0, 20 ) ),
            'billingPostalCode'  => substr( $billingAddress->getPostcode(), 0, 10 ),
            'billingState'        => ( ! empty( $billingAddress->getRegion() ) ) ? substr( $billingAddress->getRegion(), 0, 10 ) : 'XX'
        ];
        if ( ! empty( $billingAddress->getCompany() ) ) {
            $billingArr['billingCompany'] = substr( $billingAddress->getCompany(), 0, 100 );
        }

        return $billingArr;
    }

    /**
     * @param $shippingAddress
     *
     * @return array
     */
    public function getShippingArray( $shippingAddress, $billingAddress ) {
        $billingState                = ( ! empty( $billingAddress->getRegion() ) ) ? substr( $billingAddress->getRegion(), 0, 10 ) : 'XX';
        $shippingArr                 = [
            'shippingAddress1'    => ( ! empty( $shippingAddress->getStreet()[0] ) ) ? substr( $shippingAddress->getStreet()[0], 0, 40 ) : substr( $billingAddress->getStreet()[0], 0, 40 ),
            'shippingAddress2'    => ( ! empty( $shippingAddress->getStreet()[1] ) ) ? substr( $shippingAddress->getStreet()[1], 0, 40 ) : '',
            'shippingAddress3'    => ( ! empty( $shippingAddress->getStreet()[2] ) ) ? substr( $shippingAddress->getStreet()[2], 0, 40 ) : '',
            'shippingCity'         => ( ! empty( $shippingAddress->getCity() ) ) ? substr( $shippingAddress->getCity(), 0, 50 ) : substr( $billingAddress->getCity(), 0, 50 ),
            'shippingCountry' => ( ! empty( $shippingAddress->getCountryId() ) ) ? strtoupper( $shippingAddress->getCountryId() ) : strtoupper( $billingAddress->getCountryId() ),
            'shippingFirstName'   => ( ! empty( $shippingAddress->getFirstname() ) ) ? substr( $shippingAddress->getFirstname(), 0, 50 ) : substr( $billingAddress->getFirstname(), 0, 50 ),
            'shippingLastName'    => ( ! empty( $shippingAddress->getLastname() ) ) ? substr( $shippingAddress->getLastname(), 0, 50 ) : substr( $billingAddress->getLastname(), 0, 50 ),
            'shippingEmail' => ( ! empty( $shippingAddress->getRegion() ) ) ? substr( $shippingAddress->getEmail(), 0, 250 ) : substr( $billingAddress->getEmail(), 0, 250 ),
            'shippingPhone'        => ( ! empty( $shippingAddress->getTelephone() ) ) ? preg_replace( '/[^0-9]+/', '', substr( $shippingAddress->getTelephone(), 0, 20 ) ) : preg_replace( '/[^0-9]+/', '', substr( $billingAddress->getTelephone(), 0, 20 ) ),
            'shippingPostalCode'  => ( ! empty( $shippingAddress->getPostcode() ) ) ? substr( $shippingAddress->getPostcode(), 0, 10 ) : substr( $billingAddress->getPostcode(), 0, 10 ),
            'shippingState'        => ( ! empty( $shippingAddress->getRegion() ) ) ? substr( $shippingAddress->getRegion(), 0, 50 ) : $billingState
        ];

        return $shippingArr;
    }

    public function get_level3_data_from_order($order)
    {
        $items = array();
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = array(
                'product_code'                  => $item->getSku() ? $item->getSku() : $item->getProductId(),
                'product_description'           => $item->getDescription() ? $item->getDescription() : $item->getName(),
                'unit_cost'                     => $this->formatAmount($item->getPrice()),
                'quantity'                      => (int)$item->getQty(),
                'discount_amount'               => $this->formatAmount($item->getDiscountAmount()),
                'tax_amount'                    => $this->formatAmount($item->getTaxAmount()),
                'item_amount'                   => $this->formatAmount($item->getRowTotal())
            );
        }
        return $items;
    }

    public function formatAmount($amount, $asFloat = false)
    {
        return number_format((float)$amount, 2, '.', '');
    }

    /**
     * @param       $route
     * @param array $params
     *
     * @return string
     */
    public function getUrl( $route, $params = [] ) {
        return $this->_getUrl( $route, $params );
    }
    /**
     * @param $incrementId
     * @param $refNo
     *
     * @return false|\Magento\Sales\Model\Order
     */
    public function getOrderByIncrementId( $incrementId, $refNo ) {
        $foundOrder = $this->_orderFactory->create()->loadByIncrementId( $incrementId );
        if ( $foundOrder->getId() && $refNo == $foundOrder->getExtOrderId() ) {
            return $foundOrder;
        }

        return false;
    }

    /**
     * @param $incrementId
     *
     * @return \Magento\Quote\Api\Data\CartInterface|null
     */
    public function getQuoteByIncrementId( $incrementId ) {
        $sc       = $this->_searchCriteriaBuilder->addFilter( \Magento\Quote\Api\Data\CartInterface::KEY_RESERVED_ORDER_ID,
            $incrementId )->create();
        $quoteArr = $this->_quoteRepo->getList( $sc )->getItems();
        $quote    = null;

        if ( count( $quoteArr ) > 0 ) {
            $quote = current( $quoteArr );
        }

        return $quote;
    }

    /**
     * @return \Magento\Sales\Model\Service\InvoiceService
     */
    public function getInvoiceService() {
        return $this->_invoiceService;
    }

    /**
     * @return \Magento\Framework\DB\Transaction
     */
    public function getTransaction() {
        return $this->_transaction;
    }

    public function getTransactionBuilder() {
        return $this->_transactionBuilder;
    }

    public function getOrderRepo() {
        return $this->_orderRepo;
    }
}
