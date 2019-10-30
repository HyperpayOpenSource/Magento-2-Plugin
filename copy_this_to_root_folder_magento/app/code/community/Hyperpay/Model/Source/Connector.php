<?php

class Hyperpay_Model_Source_connector
{
    public function toOptionArray()
    {
        return array(
          array('value' => "migs", 'label' => Mage::helper('hyperpay')->__('MIGS')),
          array('value' => "visaacp", 'label' => Mage::helper('hyperpay')->__('VISA ACP'))
        );
    }
}