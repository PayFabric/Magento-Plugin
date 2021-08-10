<?php
namespace PayFabric\Payment\Helper\sdk\lib;
class Builder extends RequestBase {

    public $_data = array();

    public function __construct($array) {
        if (strlen($array["merchantId"]) > 0) {
            $this->merchantId = $array["merchantId"];
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'merchantId' cannot be null.");
        }
        if (strlen($array["merchantKey"]) > 0) {
            $this->merchantKey = $array["merchantKey"];
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'merchantKey' cannot be null.");
        }
    }

    protected function setToken() {
        if (strlen($this->Audience) > 0) {
            $this->_data["Audience"] = $this->Audience;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Audience' cannot be null.");
        }
        if (strlen($this->Subject) > 0) {
            $this->_data["Subject"] = $this->Subject;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Subject' cannot be null.");
        }
    }

    protected function setRefund(){
        if (strlen($this->type) > 0) {
            $this->_data["Type"] = $this->type;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Type' cannot be null.");
        }
        if (isset($this->Amount) && is_numeric($this->Amount)) {
            $this->_data["Amount"] = $this->Amount;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Amount' is invalid.");
        }
        if (strlen($this->ReferenceKey) > 0) {
            $this->_data["ReferenceKey"] = $this->ReferenceKey;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'ReferenceKey' cannot be null.");
        }
    }

    protected function setOrder() {
        if (strlen($this->type) > 0) {
            $this->_data["Type"] = $this->type;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Type' cannot be null.");
        }
        if (strlen($this->referenceNum) > 0) {
            $this->_data["TrxUserDefine1"] = $this->referenceNum;
        }
        if (isset($this->Amount) && is_numeric($this->Amount)) {
            $this->_data["Amount"] = $this->Amount;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Amount' is invalid.");
        }
        if (strlen($this->Currency) > 0 ) {
            $this->_data["Currency"] = $this->Currency;
        }else{
            throw new \InvalidArgumentException("[PayFabric Class] Field 'Currency' cannot be null.");
        }
        if (strlen($this->customerId) > 0) {
            $this->_data["Customer"] = $this->customerId;
        }
        $this->setAddress();
    }
    
    protected function setAddress() {
        if (strlen($this->billingCity) > 0) {
            $this->_data['Document']["DefaultBillTo"]["City"] = $this->billingCity;
        }
        if (strlen($this->billingCountry) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Country"] = $this->billingCountry;
        }
        if (strlen($this->customerId) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Customer"] = $this->customerId;
        }
        if (strlen($this->billingEmail) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Email"] = $this->billingEmail;
        }
        if (strlen($this->billingAddress1) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Line1"] = $this->billingAddress1;
        }
        if (strlen($this->billingAddress2) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Line2"] = $this->billingAddress2;
        }
        if (strlen($this->billingAddress3) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Line3"] = $this->billingAddress3;
        }
        if (strlen($this->billingPhone) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Phone"] = $this->billingPhone;
        }
        if (strlen($this->billingState) > 0) {
            $this->_data['Document']["DefaultBillTo"]["State"] = $this->billingState;
        }
        if (strlen($this->billingPostalCode) > 0) {
            $this->_data['Document']["DefaultBillTo"]["Zip"] = $this->billingPostalCode;
        }

        if (strlen($this->shippingCity) > 0) {
            $this->_data["Shipto"]["City"] = $this->shippingCity;
        }
        if (strlen($this->shippingCountry) > 0) {
            $this->_data["Shipto"]["Country"] = $this->shippingCountry;
        }
        if (strlen($this->customerId) > 0) {
            $this->_data["Shipto"]["Customer"] = $this->customerId;
        }
        if (strlen($this->shippingEmail) > 0) {
            $this->_data["Shipto"]["Email"] = $this->shippingEmail;
        }
        if (strlen($this->shippingAddress1) > 0) {
            $this->_data["Shipto"]["Line1"] = $this->shippingAddress1;
        }
        if (strlen($this->shippingAddress2) > 0) {
            $this->_data["Shipto"]["Line2"] = $this->shippingAddress2;
        }
        if (strlen($this->shippingAddress3) > 0) {
            $this->_data["Shipto"]["Line3"] = $this->shippingAddress3;
        }
        if (strlen($this->shippingPhone) > 0) {
            $this->_data["Shipto"]["Phone"] = $this->shippingPhone;
        }
        if (strlen($this->shippingState) > 0) {
            $this->_data["Shipto"]["State"] = $this->shippingState;
        }
        if (strlen($this->shippingPostalCode) > 0) {
            $this->_data["Shipto"]["Zip"] = $this->shippingPostalCode;
        }
    }

    protected function setItens(){
        //set level2
        $this->_data['Document']['Head'] = array(
            array('Name' => 'InvoiceNumber', 'Value' => $this->referenceNum),
            array('Name' => 'FreightAmount', 'Value' => $this->freightAmount),
            array('Name' => 'TaxAmount', 'Value' => $this->taxAmount),
        );
        //set level3
        $this->_data['Document']['Lines'] = array();
        if(!empty($this->lineItems)) {
            foreach ($this->lineItems as $item) {
                $this->_data['Document']['Lines'][]['Columns'] = array(
                    array('Name' => 'ItemProdCode', 'Value' => $item['product_code']),
                    array('Name' => 'ItemDesc', 'Value' => $item['product_description']),
                    array('Name' => 'ItemCost', 'Value' => $item['unit_cost']),
                    array('Name' => 'ItemQuantity', 'Value' => $item['quantity']),
                    array('Name' => 'ItemDiscount', 'Value' => $item['discount_amount']),
                    array('Name' => 'ItemTaxAmount', 'Value' => $item['tax_amount']),
                    array('Name' => 'ItemAmount', 'Value' => $item['item_amount']),
                );
            }
        }
        //Set UserDefined
        if (strlen($this->pluginName) > 0 && strlen($this->pluginVersion) > 0) {
            $this->_data["Document"]["UserDefined"] = array(
                array('Name' => 'PluginName', "Value" => $this->pluginName),
                array('Name' => 'PluginVersion', "Value" => $this->pluginVersion)
            );
        }
    }

    public function __get($name){
        return '';
    }

}