<?php
namespace PayFabric\Payment\Helper\sdk\lib;
class Request extends Builder {

    protected function sendXml() {
        if (is_object(RequestBase::$logger)) {
            self::$logger->logInfo('_data has been generated');
            self::$logger->logDebug(' ', json_encode($this->_data));
        }
        $curl = curl_init($this->endpoint);
        $opt = array(
            CURLOPT_HTTPHEADER => array('Content-Type: application/json',
                'Authorization: ' . $this->merchantId . '|' . $this->merchantKey
            ),
            CURLOPT_SSL_VERIFYHOST => self::$sslVerifyHost,
            CURLOPT_SSL_VERIFYPEER => self::$sslVerifyPeer,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_RETURNTRANSFER => 1);
        if (!empty($this->_data)) {
            $opt[CURLOPT_POST] = 1;
            $opt[CURLOPT_POSTFIELDS] = json_encode($this->_data);
        }
        curl_setopt_array($curl, $opt);
        $this->xmlResponse = curl_exec($curl);
        if (is_object(RequestBase::$logger)) {
        	self::$logger->logInfo('Sending data to '.$this->endpoint);
        }
        $curlInfo = curl_getinfo($curl);
        curl_close($curl);
        if (RequestBase::$debug == true) {
        	$this->printDebug($curlInfo); 
        }
        if ($this->xmlResponse) {
            if (is_object(RequestBase::$logger)) {
                self::$logger->logInfo('Response received');
                self::$logger->logDebug(' ', $this->xmlResponse);
            }
        	return $this->xmlResponse;
        }
        else { 
        	throw new \UnexpectedValueException('[PayFabric Class] Connection error with PayFabric server!', 503);
        }
    }
    
    private function printDebug($param,$_mpInfo='') {
        $this->debugger("Target URL: ".$this->endpoint);
        $this->debugger("Request: ".htmlentities(mb_convert_encoding(json_encode($this->_data), "UTF-8")));
        if ($param) {
            $this->debugger("Response: ".htmlentities(mb_convert_encoding($this->xmlResponse, "UTF-8")));
            $this->debugger("Response time: ".round($param["total_time"],3)." secs.");
        }
        else {
            $this->debugger("Response: Connection problems with PayFabric!");
            foreach ($param as $k => $v) { 
                if ($k != "certinfo") { $_mpInfo .= $k.": ".$v.", "; }
            }
            $this->debugger("cURL_getinfo data: ".$_mpInfo);
        }
    }
    
    private function debugger($string) {
        $_d = date('Y-m-d H:m:s',substr(microtime(),"11","10")).":".substr(microtime(),"2","5");
        print_r("<br>".str_repeat("-",20)."<br>[".$_d."] ".$string."<br>".str_repeat("-",20)."<br>");
    }
    
}