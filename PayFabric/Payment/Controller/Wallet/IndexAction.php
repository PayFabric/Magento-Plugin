<?php

namespace PayFabric\Payment\Controller\Wallet;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\PageFactory;
use PayFabric\Payment\Controller\WalletsManagement;

class IndexAction extends WalletsManagement
{
    /**
     * @var PageFactory
     */
    private $pageFactory;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        PageFactory $pageFactory
    ) {
        parent::__construct($context, $customerSession);
        $this->pageFactory = $pageFactory;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('My Wallets'));

        return $resultPage;
    }
}