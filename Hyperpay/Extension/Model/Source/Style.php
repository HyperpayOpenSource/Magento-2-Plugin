<?php
namespace Hyperpay\Extension\Model\Source;

class Style implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
          array('value' => 'card', 'label' => __('Card')),
          array('value' => 'plain', 'label' => __('Plain')),
          array('value' => 'none', 'label' => __('None')),

        );
    }
}