<?php
namespace Hyperpay\Extension\Model\Source;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
          array('value' => "test_Internal", 'label' => __('Integrator Test')),
          array('value' => "test", 'label' => __('Connector Test')),
          array('value' => "live", 'label' => __('Live'))
        );
    }
}