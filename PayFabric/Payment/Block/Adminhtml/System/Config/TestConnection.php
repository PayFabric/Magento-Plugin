<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayFabric\Payment\Block\Adminhtml\System\Config;

/**
 * Gateway test connection block
 * @codeCoverageIgnore
 */
class TestConnection extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * {@inheritdoc}
     */
    protected function _getFieldMapping()
    {
        $fields = [
            'environment' => 'payfabric_payment_section_environment',
            'merchant_password' => 'payfabric_payment_section_merchant_password',
            'merchant_id' => 'payfabric_payment_section_merchant_id',
            'payment_action' => 'payfabric_payment_section_payment_action'
        ];
        return $fields;
    }

    /**
     * Set template to itself
     *
     * @return $this
     * @since 100.1.0
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setTemplate('PayFabric_Payment::config-testconnection.phtml');
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @since 100.1.0
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element = clone $element;
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     * @since 100.1.0
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $this->addData(
            [
                'button_label' => __($originalData['button_label']),
                'html_id' => $element->getHtmlId(),
                'ajax_url' => $this->_urlBuilder->getUrl('payment/config/testconnection'),
                'field_mapping' => str_replace('"', '\\"', json_encode($this->_getFieldMapping()))
            ]
        );

        return $this->_toHtml();
    }

}
