<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayFabric\Payment\Block\Customer;

use PayFabric\Payment\Helper\Helper;
use Magento\Framework\View\Element\Template;
use Magento\Customer\Model\Session;

/**
 * Class Wallets
 */
class Wallets extends Template
{
    /**
     * @var \PayFabric\Payment\Helper\Helper
     */
    private $_helper;

    /**
     * @var Session
     */
    private $session;

    /**
     * Wallets constructor.
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Helper $helper,
        Session $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_helper = $helper;
        $this->session = $session;
    }

    public function getWallets(){
        $customerId = $this->session->getCustomerId();
        if (!$customerId || $this->session->isLoggedIn() === false) {
            $customerId = '';
        }
        $params['customer_id'] = $customerId;
        return $this->_helper->executeGatewayTransaction("GET_WALLETS", $params);
    }
}
