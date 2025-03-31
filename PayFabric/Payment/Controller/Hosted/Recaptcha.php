<?php

namespace PayFabric\Payment\Controller\Hosted;
use Magento\Framework\Controller\ResultFactory;
use Magento\ReCaptchaValidationApi\Api\ValidatorInterface;
use Magento\ReCaptchaWebapiApi\Api\WebapiValidationConfigProviderInterface;
use Magento\ReCaptchaWebapiApi\Model\Data\EndpointFactory;

class Recaptcha extends \Magento\Framework\App\Action\Action
{
    /**
     * @var ValidatorInterface
     */
    private $recaptchaValidator;

    /**
     * @var WebapiValidationConfigProviderInterface
     */
    private $configProvider;

    /**
     * @var EndpointFactory
     */
    private $endpointFactory;

    public function __construct(
        ValidatorInterface                      $recaptchaValidator,
        WebapiValidationConfigProviderInterface $configProvider,
        EndpointFactory                         $endpointFactory,
        \Magento\Framework\App\Action\Context   $context
    ) {
        $this->recaptchaValidator = $recaptchaValidator;
        $this->configProvider = $configProvider;
        $this->endpointFactory = $endpointFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $this->validate();
            $responseContent = ['success' => true, 'error_message' => ''];
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $responseContent = ['success' => false, 'error_message' => $e->getMessage()];
        } catch (\Exception $e) {
            $responseContent = [
                'success' => false,
                'error_message' => __('ReCaptcha validation failed, please try again')
            ];
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($responseContent);
        return $resultJson;
    }

    public function validate()
    {
        $endpointData = $this->endpointFactory->create([
            'class' => 'Magento\Checkout\Api\PaymentInformationManagementInterface',
            'method' => 'savePaymentInformationAndPlaceOrder',
            'name' => 'V1/carts/mine/payment-information'
        ]);
        $config = $this->configProvider->getConfigFor($endpointData);
        if ($config) {
            $value = (string)$this->getRequest()->getHeader('X-ReCaptcha');
            if (!$this->recaptchaValidator->isValid($value, $config)->isValid()) {
                throw new \Exception(__('ReCaptcha validation failed, please try again'));
            }
        }
        return true;
    }

}
