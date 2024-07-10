<?php
namespace Hyperpay\Extension\Model\Source;

class CreditCardOptions implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
          array('value' => 'VISA', 'label' => __('VISA')),
          array('value' => 'MASTER', 'label' => __('MASTER')),
          array('value' => 'AMEX', 'label' => __('AMEX')),
          array('value' => 'MADA', 'label' => __('MADA')),
          array('value' => 'JCB', 'label' => __('JCB')),

        );
    }
}
