<?php

namespace PayFabric\Payment\Model\Config\Source;

class DebugLog implements \Magento\Framework\Option\ArrayInterface
{
    const ON = 'INFO';
    const OFF = '';

    /**
     * Possible environment types.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::OFF,
                'label' => 'Off',
            ],
            [
                'value' => self::ON,
                'label' => 'On',
            ],
        ];
    }
}
