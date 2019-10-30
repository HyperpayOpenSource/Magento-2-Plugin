<?php


class Hyperpay_Model_Source_Style
{
    public function toOptionArray()
    {
        return array(
          array('value' => 'card', 'label' => Mage::helper('hyperpay')->__('Card')),
          array('value' => 'plain', 'label' => Mage::helper('hyperpay')->__('Plain')),
          array('value' => 'none', 'label' => Mage::helper('hyperpay')->__('None')),

        );
    }
}