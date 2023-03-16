<?php

namespace PayFabric\Payment\Controller\Wallet;

use PayFabric\Payment\Helper\Helper;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use PayFabric\Payment\Controller\WalletsManagement;
use Magento\Framework\Data\Form\FormKey\Validator;

class DeleteAction extends WalletsManagement
{

    const WRONG_REQUEST = 1;

    const WRONG_TOKEN = 2;

    const ACTION_EXCEPTION = 3;

    /**
     * @var array
     */
    private $errorsMap = [];

    /**
     * @var Validator
     */
    private $fkValidator;

    /**
     * @var \PayFabric\Payment\Helper\Helper
     */
    private $_helper;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param Validator $fkValidator
     * @param Helper $helper
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        Validator $fkValidator,
        Helper $helper
    ) {
        parent::__construct($context, $customerSession);
        $this->fkValidator = $fkValidator;
        $this->_helper = $helper;
        $this->errorsMap = [
            self::WRONG_TOKEN => __('No token found.'),
            self::WRONG_REQUEST => __('Wrong request.'),
            self::ACTION_EXCEPTION => __('Deletion failure. Please try again.')
        ];
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $request = $this->_request;
        if (!$request instanceof Http) {
            return $this->createErrorResponse(self::WRONG_REQUEST);
        }

        if (!$this->fkValidator->validate($request)) {
            return $this->createErrorResponse(self::WRONG_REQUEST);
        }

        $id = $request->getPostValue('id');
        if ($id === null) {
            return $this->createErrorResponse(self::WRONG_TOKEN);
        }
        $params['id'] = $id;

        try {
            $this->_helper->executeGatewayTransaction("DEL_WALLET", $params);
        } catch (\Exception $e) {
            return $this->createErrorResponse(self::ACTION_EXCEPTION);
        }

        return $this->createSuccessMessage();
    }

    /**
     * @param int $errorCode
     * @return ResponseInterface
     */
    private function createErrorResponse($errorCode)
    {
        $this->messageManager->addErrorMessage(
            $this->errorsMap[$errorCode]
        );

        return $this->_redirect('payfabric/wallet/indexaction');
    }

    /**
     * @return ResponseInterface
     */
    private function createSuccessMessage()
    {
        $this->messageManager->addSuccessMessage(
            __('Deleted the wallet card successfully!')
        );
        return $this->_redirect('payfabric/wallet/indexaction');
    }
}