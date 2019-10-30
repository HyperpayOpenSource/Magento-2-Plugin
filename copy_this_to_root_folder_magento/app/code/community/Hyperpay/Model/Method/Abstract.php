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
 * Abstract payment model
 *
 */
 
$ExternalLibPath=Mage::getModuleDir('', 'Hyperpay') . DS . 'core' . DS .'copyandpay.php';
require_once ($ExternalLibPath);

abstract class Hyperpay_Model_Method_Abstract extends Mage_Payment_Model_Method_Abstract
{
    
    /**
     * Is method a gateaway
     *
     * @var boolean
     */
    protected $_isGateway = true;

    /**
     * Can this method use for checkout
     *
     * @var boolean
     */
    protected $_canUseCheckout = true;

    /**
     * Can this method use for multishipping
     *
     * @var boolean
     */
    protected $_canUseForMultishipping = false;
    protected $_canRefund = true;
    /**
     * Is a initalize needed
     *
     * @var boolean
     */
    protected $_isInitializeNeeded = true;

    /**
     *
     * @var string
     */
    protected $_accountBrand = '';

    /**
     *
     * @var type
     */
    protected $_methodCode = '';

    /**
     * Payment Title
     *
     * @var type
     */
    protected $_methodTitle = '';

    /**
     * @var string
     */
    protected $_paymentCode = 'DB';

    /**
     *
     * @var string
     */
    protected $_subtype = '';

    /**
     * Magento method code
     *
     * @var string
     */
    protected $_code = 'hyperpay_abstract';

    /**
     *
     * @var string
     */
    protected $_collectData = '';

    /**
     * Redirect or iFrame
     * @var type 
     */
    protected $_implementation = 'iframe';
    
    protected $_canCapture = true;
	
	
    /**
     * Retrieve the server mode
     *
     * @return string
     */	
    public function getServerMode()
    {
        $server_mode = Mage::getStoreConfig('payment/hyperpay/server_mode', $this->getOrder()->getStoreId());
        return $server_mode;
    }
    /**
     * Retrieve the Risk Channel Id
     *
     * @return string
     */
    public function getRiskChannelId()
    {
        $risk = Mage::getStoreConfig('payment/hyperpay/riskChannelId', $this->getOrder()->getStoreId());
        return $risk;
    }
    /**
     * Retrieve the style
     *
     * @return string
     */
    public function getStyle()
    {
        $style = Mage::getStoreConfig('payment/hyperpay/style', $this->getOrder()->getStoreId());
        return $style;
    }
    /**
     * Retrieve the style
     *
     * @return string
     */
    public function getCss()
    {
        $style = Mage::getStoreConfig('payment/hyperpay/css', $this->getOrder()->getStoreId());
        return $style;
    }
    /**
     * Retrieve the transaction mode
     *
     * @return string
     */
    public function getCurrency()
    {
        $currency =  Mage::getStoreConfig('payment/' . $this->getCode() . '/currency', $this->getOrder()->getStoreId());
        return $currency;
    }
    /**
     * Retrieve the connector
     *
     * @return string
     */
    public function getConnector()
    {
        $connector =  Mage::getStoreConfig('payment/' . $this->getCode() . '/connector', $this->getOrder()->getStoreId());
        return $connector;
    }
    /**
     * Retrieve the credentials
     *
     * @return array
     */
    public function getCredentials()
    {
       $credentials = array(
            'auth'      => Mage::getStoreConfig('payment/hyperpay/auth', $this->getOrder()->getStoreId()),
            'entityId'  => Mage::getStoreConfig('payment/' . $this->getCode() . '/entityId', $this->getOrder()->getStoreId())
        );
        return $credentials;
    }

    /**
     * Return Quote or Order Object depending what the Payment is
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        $paymentInfo = $this->getInfoInstance();

        if ($paymentInfo instanceof Mage_Sales_Model_Order_Payment) {
            return $paymentInfo->getOrder();
        }

        return $paymentInfo->getQuote();
    }

    /**
     * Retrieve the order place URL
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $name = Mage::helper('hyperpay')->getNameData($this->getOrder());
        $address = Mage::helper('hyperpay')->getAddressData($this->getOrder());
        $contact = Mage::helper('hyperpay')->getContactData($this->getOrder());
        $basket = Mage::helper('hyperpay')->getBasketData($this->getOrder());

        $credentials = $this->getCredentials();
        $server = $this->getServerMode();

        Mage::getSingleton('customer/session')->setServerMode($server);



        $dataCust['first_name'] = $name['first_name'];
        $dataCust['last_name'] = $name['last_name'];
        $dataCust['street'] = $address['street'];
        $dataCust['zip'] = $address['zip'];
        $dataCust['city'] = $address['city'];
        $dataCust['country_code'] = $address['country_code'];
        $dataCust['email'] = $contact['email'];
        $dataCust['ip'] = $contact['ip'];
        $dataCust['phone'] = $contact['phone'];
        $dataCust['amount'] = $basket['baseAmount'];
        $dataCust['currency'] = $this->getCurrency();
        $dataCust['amount'] = $this->convertPrice($dataCust['amount'],$dataCust['currency']);
        $dataTransaction = $credentials;
        $dataTransaction['tx_mode'] = $this->getServerMode();
        $dataTransaction['payment_type'] = $this->getHyperpayTransactionCode();
        $dataTransaction['orderId'] = $this->getOrder()->getReservedOrderId();
        $dataTransaction['connector'] = $this->getConnector();
        $dataTransaction['risk_channel_id'] = $this->getRiskChannelId();
        $dataTransaction['method'] = $this->_methodCode;
        $postData = getPostParameter($dataCust,$dataTransaction);

        $url = getTokenUrl($server);
        
        $token = getToken($postData,$url,$server,$credentials['auth'])['id'];
        Mage::getSingleton('customer/session')->setStyle($this->getStyle());
        Mage::getSingleton('customer/session')->setCss($this->getCss());

        $jsUrl = getJsUrl($token,$server);
        Mage::getSingleton('customer/session')->setJsUrl($jsUrl);
        Mage::getSingleton('customer/session')->setIframeBrand($this->_accountBrand);
        Mage::getSingleton('customer/session')->setIframeFrontendResponse(Mage::getUrl('hyperpay/response/handleCpResponse/',array('_secure'=>true)));

        if ($token != '') {
            $this->_paymentform();
        } else {
            Mage::throwException(Mage::helper('hyperpay')->__('Error before redirect'));
        }

		return Mage::getSingleton('customer/session')->getRedirectUrl();
    }
    public function capture(Varien_Object $payment , $amount)
    {
        if ($payment->getAdditionalInformation('hyperpay_transaction_code') == 'PA') {
            $refId = $payment->getAdditionalInformation('IDENTIFICATION_REFERENCEID');
            $url = getCaptureUrl($this->getServerMode()).$refId;
            $currency = $payment->getAdditionalInformation('CURRENCY');
            $amountVal = $this->convertPrice($amount,$currency);
            $test = false;
            if($this->getServerMode() == 'live')
            {
                $test=true;
            }
            if($test) {
                $grandTotal = (int) $amountVal;
            }else {
                $grandTotal = number_format($amountVal, 2, '.', '');
            }

            $dataTransaction = $this->getCredentials();
            $dataTransaction['tx_mode'] = $this->getServerMode();
            $dataTransaction['currency'] = $currency;
            $dataTransaction['payment_type'] = "CP";
            $dataTransaction['amount'] = $grandTotal;
            $data = getPostCaptureOrRefund($dataTransaction);
            $result=getToken($data,$url,$this->getServerMode(),$dataTransaction['auth']);

            $payment->setAdditionalInformation('CAPTURE', $result['resultDetails']['ExtendedDescription']);

            if (preg_match('/^(000\.400\.0|000\.400\.100)/', $result['result']['code'])
                || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $result['result']['code'])) {
                $payment->setStatus('APPROVED')
                    ->setTransactionId($payment->getAdditionalInformation('IDENTIFICATION_REFERENCEID'))
                    ->setIsTransactionClosed(1)->save();
            } else {
                $order = $payment->getOrder();
                $order->addStatusHistoryComment($result['resultDetails']['ExtendedDescription'], false);
                $order->save();
                Mage::throwException(Mage::helper('hyperpay')->__('An error occurred while processing'));
            }
        }
        else {
            $payment->setStatus('APPROVED')
                ->setTransactionId($payment->getAdditionalInformation('IDENTIFICATION_REFERENCEID'))
                ->setIsTransactionClosed(1)->save();
        }

        return $this;
    }
    
	public function processInvoice($invoice, $payment)
    {
        $invoice->setTransactionId($payment->getLastTransId());
		
        $invoice->save(); 
        $invoice->sendEmail();
        return $this;
    }
	
    /**
     *
     * @return string
     */
    public function getAccountBrand()
    {
        return $this->_accountBrand;
    }

    /**
     *
     * @return string
     */
    public function getPaymentCode()
    {
        return $this->_methodCode . "." . $this->getHyperpayTransactionCode();
    }

    public function getHyperpayTransactionCode()
    {
        return $this->_paymentCode;
    }
    
    /**
     *
     * @return string
     */
    public function getMethod()
    {
         return $this->_methodCode;
    }

    /**
     *
     * @return string
     */
    public function getSubtype()
    {
        return $this->_subtype;
    }

    /**
     *
     * @return string
     */
    public function getCollectData()
    {
        return $this->_collectData;
    }

    /**
     * Returns Payment Title
     *
     * @return string
     */
    public function getTitle()
    {
			return Mage::getStoreConfig('payment/' . $this->getCode() . '/title', $this->getOrder()->getStoreId());

    }


    /**
     * Set the iframe Url
     * 
     * @param array $response
     */
   
    protected function _paymentform()
    {
        Mage::getSingleton('customer/session')->setIframeFlag(true);
        Mage::getSingleton('customer/session')->setRedirectUrl(Mage::app()->getStore(Mage::getDesign()->getStore())->getUrl('hyperpay/response/renderCC/', array('_secure'=>true)));
    }
    
    /**
     * Retrieve implementation method
     * 
     * @return string
     */
    protected function _getImplementation()
    {
        return $this->_implementation;
    }
    /**
     * Convert amount of payment from base currency to selected currency
     *
     * @param  $payment
     * @param  $amountValue
     * @return double
     */
    public function convertPrice($amountValue,$currentCurrency)
    {
        $baseCurrency = Mage::app()->getStore()->getBaseCurrencyCode();
        if ($currentCurrency != $baseCurrency) {
            try
            {
                $amountValue = Mage::app()->getStore()->getBaseCurrency()->
                convert($amountValue, $baseCurrency);
            }
            catch (\Exception $e)
            {
                Mage::log("you have to add conversion rate", Zend_Log::ERR);
                Mage::throwException(Mage::helper('hyperpay')->__('You have to add conversion rate'));

            }
        }

        return $amountValue;
    }
    public function refund(Varien_Object $payment, $amount)
    {
        $refId = $payment->getAdditionalInformation('IDENTIFICATION_REFERENCEID');
        $url = getCaptureUrl($this->getServerMode()).$refId;
        $currency = $payment->getAdditionalInformation('CURRENCY');
        $amountVal = $this->convertPrice($amount,$currency);
        $test = false;
        if($this->getServerMode() == 'live')
        {
            $test=true;
        }
        if($test) {
            $grandTotal = (int) $amountVal;
        }else {
            $grandTotal = number_format($amountVal, 2, '.', '');
        }

        $dataTransaction = $this->getCredentials();
        $dataTransaction['tx_mode'] = $this->getServerMode();
        $dataTransaction['currency'] = $currency;
        $dataTransaction['payment_type'] = "RF";
        $dataTransaction['amount'] = $grandTotal;
        $data = getPostCaptureOrRefund($dataTransaction);
        $result=getToken($data,$url,$this->getServerMode());
        $payment->setAdditionalInformation('Refund', $result['result']['description']);

        if (preg_match('/^(000\.400\.0|000\.400\.100)/', $result['result']['code'])
            || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $result['result']['code'])) {
            $order = $payment->getOrder();
            $order->addStatusHistoryComment($result['result']['description']);
            $order->save();
        } else {
            $order = $payment->getOrder();
            $order->addStatusHistoryComment($result['result']['description'], false);
            $order->save();
            Mage::throwException(Mage::helper('hyperpay')->__('An error occurred while processing'));
        }
        return $this;
    }
}

