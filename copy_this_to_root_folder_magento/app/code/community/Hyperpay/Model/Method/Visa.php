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
 * Creditcard payment model
 *
 */
class Hyperpay_Model_Method_Visa extends Hyperpay_Model_Method_Abstract
{
    
    /**
     * Magento method code
     *
     * @var string
     */
    protected $_code = 'hyperpay_visa';
    protected $_formBlockType = 'hyperpay/payment_form_visa';

    /**
     *
     * @var string
     */
    protected $_methodCode = 'visa';
    protected $_accountBrand = 'VISA';

    /**
     * Payment Title
     *
     * @var type
     */
    protected $_methodTitle = 'Visa';

    
    public function getHyperpayTransactionCode()
    {
        if (Mage::app()->getStore()->isAdmin()) {
            return 'CP';
        }
            
        return Mage::getStoreConfig('payment/' . $this->getCode() . '/transaction_mode', $this->getOrder()->getStoreId());
    }
    
    /**
     *
     * @return string
     */
    public function getPaymentCode()
    {
        return $this->_methodCode . "." . $this->getHyperpayTransactionCode();
    }
}
