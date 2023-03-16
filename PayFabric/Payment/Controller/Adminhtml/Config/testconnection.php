<?php
/**
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayFabric\Payment\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\AdvancedSearch\Model\Client\ClientResolver;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filter\StripTags;
use PayFabric\Payment\Helper\sdk\lib\Payments;
use PayFabric\Payment\Model\Config\Source\Environment;
use PayFabric\Payment\Model\Config\Source\NewOrderPaymentActions;
use Magento\Store\Model\StoreManagerInterface;

class TestConnection extends Action
{
    /**
     * Authorization level of a basic admin session.
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Payment::payment';

    /**
     * @var ClientResolver
     */
    private $clientResolver;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var StripTags
     */
    private $tagFilter;

    /**
     * @var storeManager
     */
    private $storeManager;

    /**
     * @param Context           $context
     * @param ClientResolver    $clientResolver
     * @param JsonFactory       $resultJsonFactory
     * @param StripTags         $tagFilter
     * @param StoreManagerInterface         $storeManager
     */
    public function __construct(
        Context $context,
        ClientResolver $clientResolver,
        JsonFactory $resultJsonFactory,
        StripTags $tagFilter,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->clientResolver = $clientResolver;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->tagFilter = $tagFilter;
        $this->storeManager = $storeManager;
    }

    /**
     * Check for connection to server
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = [
            'success' => false,
            'errorMessage' => '',
        ];
        $options = $this->getRequest()->getParams();

        try {
            $api_merchant_id = $options['merchant_id'];
            $api_password = $options['merchant_password'];
            $environment = $options['environment'];
            $payment_action = $options['payment_action'];
            $maxiPago = new Payments();

            // Set your credentials before any other transaction methods
            $maxiPago->setCredentials($api_merchant_id, $api_password);
            $maxiPago->setEnvironment($environment);
            $data = array(
                'Amount' => '0.01',
                'Currency' => $this->storeManager->getStore()->getBaseCurrencyCode()
            );
            $payment_action == NewOrderPaymentActions::PAYMENT_ACTION_AUTH ? $maxiPago->creditCardAuth($data) : $maxiPago->creditCardSale($data);

            $responseTran = json_decode($maxiPago->response);
            if(empty($responseTran->Key)){
                throw new \Magento\Framework\Exception\LocalizedException(__($maxiPago->response));
            }else {
                $result['success'] = true;
            }
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $result['errorMessage'] = $e->getMessage();
        } catch (\Exception $e) {
            $message = __($e->getMessage());
            $result['errorMessage'] = $this->tagFilter->filter($message);
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }
}
