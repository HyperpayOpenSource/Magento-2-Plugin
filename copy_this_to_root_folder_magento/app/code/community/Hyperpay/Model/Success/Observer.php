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
 * Order success observer
 *
 */
class Hyperpay_Model_Success_Observer 
{
    
    /**
     * Reactivate the cart because the order isn't finished
     * 
     * @param Varien_Event_Observer $observer 
     */
    public function activateQuote(Varien_Event_Observer $observer)
    {
	
        if(is_subclass_of($observer->getEvent()->getQuote()->getPayment()->getMethodInstance(),Hyperpay_Model_Method_Abstract)){
                $observer->getEvent()->getQuote()->setIsActive(true)->save();
        }

    }
}

