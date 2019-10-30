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
 * Hyperpay helper
 *
 */
class Hyperpay_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Retrieve quote object
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return Mage::getModel('checkout/session')->getQuote();
    }


    /**
     * Retrieve order object
     *
     * @param int $id
     * @return Mage_Sales_Model_Order
     */
    public function getOrder($id)
    {
        return Mage::getSingleton('sales/order')->load($id);
    }

    /**
     * Retrieve customer data
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getCustomerData($order)
    {
        $data = array(
            'name_data'    => $this->getNameData($order),
            'address_data' => $this->getAddressData($order),
            'contact_data' => $this->getContactData($order)
        );

        return $data;
    }

    /**
     * Retrieve name data
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getNameData($order)
    {
        $dob = '';
        if(!is_null($order->getCustomerDob())) {
            $dob = new Zend_Date($order->getCustomerDob());
            $dob = $dob->toString("yyyy-MM-dd");
        }
        $data = array(
            'first_name' => $order->getBillingAddress()->getFirstname(),
            'last_name'  => $order->getBillingAddress()->getLastname(),
            'sex'        => $this->getGender($order),
            'dob'        => $dob,
            'company'    => $order->getBillingAddress()->getCompany(),
            'salutation' => $this->getPrefix($order)
        );

        return $data;
    }

    /**
     * Retrieve address data
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getAddressData($order)
    {
        $data = array(
            'country_code' => $order->getBillingAddress()->getCountryId(),
            'street'       => str_replace("\n", " ", $order->getBillingAddress()->getStreetFull()),
            'zip'          => $order->getBillingAddress()->getPostcode(),
            'city'         => $order->getBillingAddress()->getCity(),
            'state'        => $order->getBillingAddress()->getRegion(),
        );

        return $data;
    }

    /**
     * Retrieve contact data
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getContactData($order)
    {
        $data = array(
            'email' => $order->getCustomerEmail(),
            'phone' => $order->getBillingAddress()->getTelephone(),
            'ip'    => Mage::helper('core/http')->getRemoteAddr(false)
        );

        return $data;
    }

    /**
     * Retrieve Basket data
     *
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    public function getBasketData($order)
    {
        if ( $order instanceof Mage_Sales_Model_Order ) {
            $basket = array(
                'amount' => $order->getGrandTotal(),
                'currency' => $order->getOrderCurrencyCode(),
                'baseCurrency' => $order->getBaseCurrencyCode(),
                'baseAmount' => $order->getBaseGrandTotal()
            );
        } 
        else if ( $order instanceof Mage_Sales_Model_Quote ) {
            $basket = array(
                'amount' => $order->getGrandTotal(),
                'currency' => $order->getQuoteCurrencyCode(),
                'baseCurrency' => $order->getBaseCurrencyCode(),
                'baseAmount' => $order->getBaseGrandTotal()
            );
        }

        return $basket;
    }

    /**
     * Retrieve method code for config loading
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getMethodCode(Mage_Sales_Model_Order $order)
    {
        return str_replace('hyperpay', 'method', $order->getPayment()->getMethod());
    }

    /**
     * This method returns the customer gender code
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getGender($order)
    {
        $gender = $order->getCustomerGender();
        if ($gender) {
            $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('customer', 'gender');
            $option = $attribute->getFrontend()->getOption($gender);

            switch (strtolower($option)) {
                case 'male':
                    return 'M';
                case 'female':
                    return 'F';
            }
        }
        return '';
    }

    /**
     * Retrieve the prefix
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getPrefix($order)
    {
        $gender = $order->getCustomerPrefix();
        if ($gender) {
            switch (strtolower($gender)) {
                case 'herr':
                case 'mr':
                    return 'MR';
                case 'frau':
                case 'mrs':
                    return 'MRS';
                case 'fräulein':
                case 'ms':
                    return 'MS';

            }
        }
        return '';
    }

    /**
     * Retrieve the locale code in iso (2 chars)
     *
     * @return string
     */
    public function getLocaleIsoCode()
    {
        return substr(Mage::app()->getLocale()->getLocaleCode(), 0, 2);
    }
    
    public function invoice(Mage_Sales_Model_Order $order)
    {
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $invoice->getOrder()->setCustomerNoteNotify(false);
        $invoice->getOrder()->setIsInProcess(true);
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();
		
        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
    }
}

