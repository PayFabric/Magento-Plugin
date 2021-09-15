<?php
namespace PayFabric\Payment\Helper\sdk\lib;
class Payments extends ResponseBase {

    private $request;
    public $response;

    public function __construct(){
        /*Define live and test gateway host */
        !defined('LIVEGATEWAY') && define('LIVEGATEWAY' , 'https://www.payfabric.com');
        !defined('TESTGATEWAY') && define('TESTGATEWAY' , 'https://dev-us2.payfabric.com');

        /*
        * Define log dir, severity level of logging mode and whether enable on-screen debug ouput.
        * PLEASE DO NOT USE "DEBUG" LOGGING MODE IN PRODUCTION
        */
//        !defined('PayFabric_LOG_SEVERITY') && define('PayFabric_LOG_SEVERITY' , 'INFO');
        !defined('PayFabric_LOG_SEVERITY') && define('PayFabric_LOG_SEVERITY' , 'DEBUG');
        !defined('PayFabric_LOG_DIR') && define('PayFabric_LOG_DIR' , BP . '/var/log');
        !defined('PayFabric_DEBUG') && define('PayFabric_DEBUG' , false);
    }

    /**
     * Performs a credit card auth
     *
     * a auth transaction and need to be captured later.
     *
     * @param array $array
     * @throws BadMethodCallException
     */
    public function creditCardAuth($array) {
        try {
            if (!is_array($array)) { 
            	throw new \BadMethodCallException('[PayFabric Class] Method '.__METHOD__.' must receive array as input');
            }
            if (is_object(RequestBase::$logger)) {
            	RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }
            $this->request = $array;
            $req = new Request($this->credentials);
            $req->setVars($this->request);
            $req->setEndpoint($this->host.'/payment/api/transaction/create');
            $req->setTransactionType("Authorization");
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Performs a credit card capture
     * 
     * Capturing a transaction confirms and completes the order.
     * 
     * @param string $key
     * @throws BadMethodCallException
     */
    public function creditCardCapture($array) {
        try {
            if (!is_array($array)) {
            	throw new \BadMethodCallException('[PayFabric Class] Method '.__METHOD__.' must receive array as input');
            }
            if (is_object(RequestBase::$logger)) {
            	RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }

            $this->request = $array;
            $req = new Request($this->credentials);
            $req->setVars($this->request);
            $req->setEndpoint($this->host.'/payment/api/transaction/process');
            $req->setTransactionType("Capture");
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Performs a credit card sale
     * 
     * A Sale transaction combines the  authorization  and the
     * capture in a single request. When performing a Sale
     * PayFabric! sends the credit card for authorization and
     * immediately captures that transaction, if approved.
     * The response sent is final.
     * 
     * @param array $array
     * @throws BadMethodCallException
     */
    public function creditCardSale($array) {
        try {
            if (!is_array($array)) { 
            	throw new \BadMethodCallException('[PayFabric Class] Method '.__METHOD__.' must receive array as input');
            }
            if (is_object(RequestBase::$logger)) {
            	RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }
            $this->request = $array;
            $req = new Request($this->credentials);
            $req->setVars($this->request);
            $req->setEndpoint($this->host.'/payment/api/transaction/create');
            $req->setTransactionType("Sale");
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Performs a host payment page token
     *
     * Integrating cashier UI needs a token after create a transaction
     *
     * @param array $array
     * @throws BadMethodCallException
     */
    public function token($array) {
        try {
            if (is_object(RequestBase::$logger)) {
                RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }
            $this->request = $array;
            $req = new Request($this->credentials);
            $req->setVars($this->request);
            $req->setEndpoint($this->host.'/payment/api/jwt/create');
            $req->setTransactionType("Token");
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Retrieve a transaction
     *
     * @param string $key
     * @throws BadMethodCallException
     */
    public function retrieveTransaction($key) {
        try {
            if (is_object(RequestBase::$logger)) {
                RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }
            $req = new Request($this->credentials);
            $req->setEndpoint($this->host.'/payment/api/transaction/'.$key);
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Performs a credit card void
     * 
     * A transaction can be Voided until the closing of the
     * final batch of the day, allowing the Merchant to cancel 
     * a transaction before any funds change hands.
     * 
     * @param string $key
     * @throws BadMethodCallException
     */
    public function creditCardVoid($key) {
        try {
            if (empty($key)) {
            	throw new \BadMethodCallException('[PayFabric Class] Method '.__METHOD__.' must receive array as input');
            }
            if (is_object(RequestBase::$logger)) {
            	RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }
            $req = new Request($this->credentials);
            $req->setEndpoint($this->host.'/payment/api/reference/' .$key. '?trxtype=Void');
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Performs a credit card refund
     * 
     * A  Return  (or  Refund) is the reversal of a credit
     * card transaction, where the funds are taken from the
     * Merchant and given back to the Card Holder. This is
     * a financial operation that usually takes a few days
     * to be completed.
     * 
     * @param array $array
     * @throws BadMethodCallException
     */
    public function creditCardRefund($array) {
        try {
            if (!is_array($array)) { 
            	throw new \BadMethodCallException('[PayFabric Class] Method '.__METHOD__.' must receive array as input');
            }
            if (is_object(RequestBase::$logger)) {
            	RequestBase::$logger->logNotice('Calling method '.__METHOD__);
            }
            $this->request = $array;
            $req = new Request($this->credentials);
            $req->setVars($this->request);
            $req->setEndpoint($this->host.'/payment/api/transaction/process');
            $req->setTransactionType("Refund");
            $this->response = $req->processRequest();
        }
        catch (\Exception $e) {
            throw $e;
        }
    }
    
}
