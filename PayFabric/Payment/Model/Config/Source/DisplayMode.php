<?php

namespace PayFabric\Payment\Model\Config\Source;

class DisplayMode implements \Magento\Framework\Option\ArrayInterface
{
    const DISPLAY_MODE_REDIRECT = 'redirect';
    const DISPLAY_MODE_IFRAME = 'iframe';
    const DISPLAY_MODE_IN_PLACE = 'in_place';

    /**
     * Possible display modes.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::DISPLAY_MODE_REDIRECT,
                'label' => 'Redirect',
            ],
            [
                'value' => self::DISPLAY_MODE_IFRAME,
                'label' => 'Dialog',
            ],
            [
                'value' => self::DISPLAY_MODE_IN_PLACE,
                'label' => 'Iframe',
            ],
        ];
    }
}
