<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 *
 * @package     Hyperpay
 * @copyright   Copyright (c) 2014 HYPERPAY
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Creditcard Mode model
 * 
 */
class Hyperpay_Model_Source_Mode 
{
    /**
     * Define which transaction types are possible
     *
     * @return array
     */
    public function toOptionArray()
    {
     return array(
            array('value' => "test_Internal", 'label' =>Mage::helper('hyperpay')->__('Integrator Test')),
            array('value' => "test", 'label' => Mage::helper('hyperpay')->__('Connector Test')),
            array('value' => "live", 'label' => Mage::helper('hyperpay')->__('Live'))
        );
       /* $modes = array(
            array(
                'label' => Mage::helper('hyperpay')->__('TEST'),
                'value' => 'TEST'
            ), 
            array(
               'label' => Mage::helper('hyperpay')->__('LIVE'),
               'value' => 'LIVE'
            )
        );
        return $modes;*/
    }
}
