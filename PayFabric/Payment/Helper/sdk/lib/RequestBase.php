<?php
namespace PayFabric\Payment\Helper\sdk\lib;
class RequestBase {
    
    protected $version = '1.0.0';
    protected $timeout = 60;
    protected static $sslVerifyPeer = 0;
    protected static $sslVerifyHost = 2;
    public static $logger;
    public static $loggerSev;
    public static $debug;

    public function setEndpoint($param) {
        try {
            if (!$param) { 
            	throw new \BadMethodCallException('[PayFabric Class] INTERNAL ERROR on '.__METHOD__.' method: no Endpoint defined');
            }
            $this->endpoint = $param;
            if (is_object(RequestBase::$logger)) {
            	RequestBase::$logger->logDebug('Setting endpoint to "'.$param.'"');
            }
        }
        catch (\Exception $e) {
            if (is_object(self::$logger)) { 
            	self::$logger->logCrit($e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()); 
            }
            throw $e;
        }
    }
    
    public function setTransactionType($param) {
        try {
            if (!$param) { 
            	throw new \BadMethodCallException('[PayFabric Class] INTERNAL ERROR on '.__METHOD__.' method: no Transaction Type defined');
            }
            $this->type = $param;
        }
        catch (\Exception $e) {
        	if (is_object(self::$logger)) { 
        		self::$logger->logCrit($e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()); 
        	}
            throw $e;
        }
    }
    
    public function setVars($array) {
        try {
            if (!$array) { 
            	throw new \BadMethodCallException('[PayFabric Class] INTERNAL ERROR on '.__METHOD__.' method: no array to format.', 400);
            }
            foreach($array as $k => $v) { 
            	$this->$k = $v; 
            }
            if (is_object(self::$logger)) { 
                if (self::$loggerSev != 'DEBUG') { 
                	$array = self::clearForLog($array); 
                }
                self::$logger->logNotice('Parameters sent', $array);
            }
        }
        catch (\Exception $e) {
        	if (is_object(self::$logger)) { 
        		self::$logger->logCrit($e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()); 
        	}
            throw $e;
        }
    }
    
    public static function setSslVerify($param) {
    	self::$sslVerifyHost = $param;
    	self::$sslVerifyPeer = $param;
    }
    
    public static function setLogger($path, $severity='INFO') {
        switch ($severity) {
            case "EMERG":
                self::$logger = new KLogger($path, KLogger::EMERG);
                break;
            case "ALERT":
                self::$logger = new KLogger($path, KLogger::ALERT);
                break;
            case "CRIT":
                self::$logger = new KLogger($path, KLogger::CRIT);
                break;
            case "ERR":
                self::$logger = new KLogger($path, KLogger::ERR);
                break;
            case "WARN":
                self::$logger = new KLogger($path, KLogger::WARN);
                break;
            case "NOTICE":
                self::$logger = new KLogger($path, KLogger::NOTICE);
                break;
            case "INFO": // Severities INFO and up are safe to use in Production as Credit Card info are NOT logged
                self::$logger = new KLogger($path, KLogger::INFO);
                break;
            case "DEBUG": // Do NOT use 'DEBUG' for Production environment as Credit Card info WILL BE LOGGED
                self::$logger = new KLogger($path, KLogger::DEBUG);
                break;
        }
        if (self::$logger->_logStatus == 1) { 
        	self::$loggerSev = $severity; 
        }
        else { 
        	self::$logger = null; 
        }
    }
    
    public static function clearForLog($text) {
        if ((!isset($text)) || (self::$loggerSev == 'DEBUG')) { 
        	return $text; 
        }
        elseif (is_array($text)) {
            isset($text["cvvNumber"]) && @$text["cvvNumber"] = str_ireplace($text["cvvNumber"], str_repeat("*", strlen($text["cvvNumber"])), $text["cvvNumber"]);
            isset($text["shippingEmail"]) && @$text["shippingEmail"] = str_ireplace($text["shippingEmail"], substr_replace($text["shippingEmail"], str_repeat("*", 3), 1, 3), $text["shippingEmail"]);
            if (isset($text["number"]) && ServiceBase::checkCreditCard(@$text["number"])) {
            	@$text["number"] = str_ireplace($text["number"], substr_replace($text["number"], str_repeat('*',strlen($text["number"])-4),'4'), $text["number"]); 
            }
            return $text;
        }
        elseif (strlen($text) >= 8) { 
        	return substr_replace($text, str_repeat('*',strlen($text)-4),'4'); 
        }
        else { 
        	return substr_replace($text, str_repeat('*', strlen($text)-2),'2'); 
        }
    }
    
    public function processRequest() {
        try {
            switch(strtolower($this->type)){
                case "token":
                    $this->setToken();
                    break;
                case "authorization":
                case "sale":
                    $this->setOrder();
                    //set level 2/3
                    $this->setItens();
                    break;
                case "refund":
                    $this->setRefund();
                    break;
                default:
                    break;
            }
            return $this->sendXml();
        }
        catch (\Exception $e) {
        	if (is_object(self::$logger)) { self::$logger->logCrit($e->getMessage()." in ".$e->getFile()." on line ".$e->getLine()); }
            throw $e;
        }
    }
}
