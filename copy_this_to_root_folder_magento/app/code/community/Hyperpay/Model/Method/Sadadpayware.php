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

class Hyperpay_Model_Method_Sadadpayware extends Hyperpay_Model_Method_Abstract
{

    /**
     * Path for payment form block
     *
     * @var string
     */
    protected $_formBlockType = 'hyperpay/payment_form_sadadpayware';

    /**
     * Magento method code
     *
     * @var string
     */
    protected $_code = 'hyperpay_sadadpayware';

    /**
     *
     * @var string
     */
    protected $_methodCode = 'sadadpayware';

    protected $_accountBrand = 'SADAD';
    
    /**
     * Payment Title
     *
     * @var type
     */
    protected $_methodTitle = 'Sadad';
    /**
     * Retrieve the order place URL
     *
     * @return string
     */
    /**
     * Retrieve the credentials
     *
     * @return array
     */
    public function getCredentials()
    {
        $credentials = array(
            'apiuser'      => Mage::getStoreConfig('payment/' . $this->getCode() . '/apiuser', $this->getOrder()->getStoreId()),
            'apisecret'  => Mage::getStoreConfig('payment/' . $this->getCode() . '/apisecret', $this->getOrder()->getStoreId()),
            'merchantid'    => Mage::getStoreConfig('payment/' . $this->getCode() . '/merchantid', $this->getOrder()->getStoreId())
        );
        return $credentials;
    }
    public function getOrderPlaceRedirectUrl()
    {
        $basket = Mage::helper('hyperpay')->getBasketData($this->getOrder());

        $credentials = $this->getCredentials();
        $server = $this->getServerMode();
        $test = true;
        if ($server == 'live')
        {
            $test=false;
        }
        Mage::getSingleton('customer/session')->setServerMode($server);

        $serviceUrl = getSadadReqUrl($server);

        $amount = $basket['baseAmount'];
        $currency = $this->getCurrency();
        $amount = $this->convertPrice($amount,$currency);
        $reqArray = array("api_user_name"=>$credentials['apiuser'],
            "api_secret"=>$credentials['apisecret'],
            "merchant_id"=>$credentials['merchantid'],
            "transaction_number"=>$this->getOrder()->getReservedOrderId(),
            "success_url"=>Mage::getUrl('hyperpay/response/handleSadadResponse/',array('_secure'=>true)),
            "failure_url"=>Mage::getUrl('hyperpay/response/handleSadadResponse/',array('_secure'=>true)),
            "lang"=>'EN',
            "is_testing"=>$test,
            "amount"=>$amount);
        $data = json_encode($reqArray);
        $decodedData = sadadCurlRequest($serviceUrl,$data,$test);
        if ($decodedData != '') {
            $stagingCheckoutUrl = getSadadRedirectUrl($server) . $decodedData;
            $this->_paymentformsadad($stagingCheckoutUrl);
        } else {
            Mage::throwException(Mage::helper('hyperpay')->__('Error before redirect'));
        }

        return Mage::getSingleton('customer/session')->getRedirectUrl();
    }
    /**
     * Set the iframe Url
     *
     * @param array $response
     */

    protected function _paymentformsadad($stagingCheckoutUrl)
    {
        Mage::getSingleton('customer/session')->setIframeFlag(true);
        Mage::getSingleton('customer/session')->setRedirectUrl($stagingCheckoutUrl);
    }
}
