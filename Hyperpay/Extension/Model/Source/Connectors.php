<?php
namespace Hyperpay\Extension\Model\Source;

class Connectors implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
          array('value' => "migs", 'label' => __('MIGS')),
          array('value' => "visaacp", 'label' => __('VISA ACP'))
        );
    }
}