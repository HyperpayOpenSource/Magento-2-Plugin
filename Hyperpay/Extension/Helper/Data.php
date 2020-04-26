<?php
namespace Hyperpay\Extension\Helper;

use \Magento\Sales\Model\Order as OrderStatus;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{

    /**
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $_jsonHelper;
    /**
     *
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $_curlClient;
    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter
     */
    protected $_adapter;
    /**
     *
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;
    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    protected $_responseFactory;
    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
    /**
     *
     * @var \\Magento\Framework\View\Asset\Repository
     */
    protected $_assetRepo;
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Helper\Context       $context
     * @param \Magento\Framework\Json\Helper\Data         $jsonHelper
     * @param \Magento\Framework\HTTP\Client\Curl         $curl
     * @param \Magento\Store\Model\StoreManagerInterface  $storeManager
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Hyperpay\Extension\Model\Adapter           $adapter
     * @param \Magento\Framework\App\ResponseFactory      $responseFactory
     * @param \Magento\Checkout\Model\Session             $checkoutSession
     * @param \Magento\Framework\View\Asset\Repository    $assetRepo
     */ 
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Message\ManagerInterface $messageManager, 
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\View\Asset\Repository $assetRepo
    ) 
    { 
        $this->_checkoutSession = $checkoutSession;
        $this->_responseFactory = $responseFactory;
        $this->_jsonHelper = $jsonHelper;
        $this->_curlClient = $curl;
        $this->_storeManager=$storeManager;
        $this->_messageManager = $messageManager;
        $this->_adapter =$adapter;
        $this->_assetRepo = $assetRepo;
        parent::__construct($context);
    }
    /**
     * Set Headers for curl request
     *
     * @param $data
     */
    public function setHeaders($headers)
    {

        $this->_curlClient->setHeaders($headers);
    }
    /**
     * Set Headers for curl request 
     *
     * @param $data
     */  
    public function setSadadHeaders($data)
    {
        $headers = array(
        'Content-Type'=>'application/json',
        'Content-Length'=> strlen($data));

        $this->_curlClient->setHeaders($headers);
    }
    /**
     * throw a new error exception
     *
     * @param $payment
     */ 
    public function doError($string)
    {
        throw new \Exception($string);
    }
    /**
     * Retrieve payment brand depending on payment method 
     *
     * @return string
     */  
    public function getBrand()
    {  
        try{
            if(!($this->_checkoutSession->getLastRealOrderId())) {
                $this->doError('Order id does not found');
            }   
            $order = $this->_checkoutSession->getLastRealOrder();
            $payment= $order->getPayment();
            $code = $payment->getData('method');
            $paymentMethod='';
            switch ($code) {
                case 'HyperPay_Visa':
                    $paymentMethod ='VISA';
                break;
                case 'HyperPay_Mada':
                    $paymentMethod ='MADA';
                    break;
                case 'HyperPay_SadadNcb':
                    $paymentMethod= 'SADAD';
                break;
                case 'HyperPay_PayPal':
                    $paymentMethod= 'PAYPAL';
                break;
                case 'HyperPay_Master':
                    $paymentMethod= 'MASTER';
                break;
                case 'HyperPay_Amex':
                    $paymentMethod= 'AMEX';
                break;
                case 'HyperPay_ApplePay':
                    $paymentMethod = 'APPLEPAY';
		break;
                case 'HyperPay_stc':
                    $paymentMethod= 'STC_PAY';
    
                break;
            }

            return $paymentMethod;
            } catch(\Exception $e) 
            {
            $this->_messageManager->addError($e->getMessage());
            $this->_logger->critical($e->getMessage());
            $this->_responseFactory->create()->setRedirect($this->_storeManager->
            getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))->sendResponse(); 
        } 
        
    }
    /**
     * Retrieve payment brand depending on payment method 
     *
     * @param  $street
     * @param  $type
     * @return string
     */
    public function getStreetAddresses($street,$type)
    {
        $streetAdd="";
        foreach ($street as $key => $value) {
            if($key == '2')
                break;
            $end = $key+1;
            $streetAdd.="&".$type."."."street".$end."=".$street[$key];
        }

        return $streetAdd;
    }
    /**
     * Retrieve Increment order id to status view
     *
     * @return string
     */ 
    public function getOrderId()
    {
        try{
        if(!($this->_checkoutSession->getLastRealOrderId())) {
                $this->doError('Order id does not found');
            }   

        return $this->_checkoutSession->getLastRealOrderId();
        } catch(\Exception $e)
        {
            $this->_messageManager->addError($e->getMessage());
            $this->_logger->critical($e->getMessage());
            $this->_responseFactory->create()->setRedirect($this->_storeManager->
            getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))->sendResponse();
        }
    }
    /**
     * Convert amount of payment from base currency to selected currency 
     *
     * @param  $payment
     * @param  $amountValue
     * @return double
     */  
    public function convertPrice($payment,$amountValue)
    {   
        $currentCurrency = $this->_adapter->getSupportedCurrencyCode($payment->getData('method'));
        $baseCurrency = $this->_storeManager->getStore()->getBaseCurrency()->getCode();
        if ($currentCurrency != $baseCurrency) {
            try 
            {
                $amountValue = $this->_storeManager->getStore()->getBaseCurrency()->
                convert($amountValue, $currentCurrency);
            }
            catch (\Exception $e)
            {
                $this->catchExceptionRedirectAndCancelOrder($payment->getOrder(), $e);
                return;
            }
        }

        return $amountValue;
    }
    /**
     * Set curl options depending on server mode 
     */  
    public function setCurlOptions()
    {
        if($this->_adapter->getEnv()) {
            $this->_curlClient->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->_curlClient->setOption(CURLOPT_SSL_VERIFYHOST, false);
        } else {
            $this->_curlClient->setOption(CURLOPT_SSL_VERIFYPEER, true);
        }

    }
    /**
    * method to check if test passed is English
     *
    * @param  (string) $text to be checked.
    * @return (bool) true|false.
    */
    public function isThisEnglishText($text)
    {
        return preg_match("/^[\w\s\.\-\,]*$/", $text);      
    }
    /**
     * Retrieve billing And shipping Address
     *
     * @param  $order
     * @return string
     */  
    public function getBillingAndShippingAddress($order)
    {
        $data="";
        $payment = $order->getPayment();
        $method = $payment->getData('method');
      $shippingAddress = $order->getShippingAddress();
    if(isset($shippingAddress) && !empty($shippingAddress)){
        $firstNameShipping = $order->getShippingAddress()->getFirstname();
        $surNameShipping = $order->getShippingAddress()->getLastname();
        $countryShipping = $order->getShippingAddress()->getCountryId();
        $telShipping= $order->getShippingAddress()->getTelephone();
        $postCodeShipping = $order->getShippingAddress()->getPostcode();
        $streetShipping = $order->getShippingAddress()->getStreet();
        $cityShipping = $order->getShippingAddress()->getCity();
        $streetShippingCompare = implode(',', $streetShipping);

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($cityShipping)==false)) {
            $data.="&shipping.city=".$cityShipping; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($countryShipping)==false)) {
            $data.="&shipping.country=".$countryShipping; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($postCodeShipping)==false)) {
            $data.="&shipping.postcode=".$postCodeShipping; 
        }
        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($firstNameShipping)==false)) {
            $data.="&shipping.customer.givenName=".$firstNameShipping; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($surNameShipping)==false)) {
            $data.="&shipping.customer.surname=".$surNameShipping; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($telShipping)==false)) {
            $data.="&shipping.customer.phone=".$telShipping; 
        }
        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($streetShippingCompare)==false)) {
            $data.=$this->getStreetAddresses($streetShipping, "shipping"); 
        }
    }

        $firsName = $order->getBillingAddress()->getFirstname();
        $surName = $order->getBillingAddress()->getLastname();
        $country = $order->getBillingAddress()->getCountryId();
        $tel= $order->getBillingAddress()->getTelephone();
        $postCode = $order->getBillingAddress()->getPostcode();
        $street = $order->getBillingAddress()->getStreet();
        $city = $order->getBillingAddress()->getCity();
        $streetCompare = implode(',', $street);
        


        
      

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($city)==false)) {
            $data.="&billing.city=".$city; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($country)==false)) {
            $data.="&billing.country=".$country; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($firsName)==false)) {
            $data.="&customer.givenName=".$firsName; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($tel)==false)) {
            $data.="&customer.phone=".$tel; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($postCode)==false)) {
            $data.="&billing.postcode=".$postCode; 
        }

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($surName)==false)) {
            $data.="&customer.surname=".$surName; 
        }

        

        if(!($this->_adapter->getConnector($method)=='migs' && $this->isThisEnglishText($streetCompare)==false)) {
            $data.=$this->getStreetAddresses($street, "billing"); 
        }

        


        return $data;
    }
    /**
     * Set order status to cancel, add message, and redirect to home page  
     *
     * @param $order
     * @param $e
     */  
    public function catchExceptionRedirectAndCancelOrder($order,$e)
    {
        $order->setState(OrderStatus::STATE_CANCELED);
        $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), OrderStatus::STATE_CANCELED);
        $order->save();
        $this->_messageManager->addError($e->getMessage());
        $this->_logger->critical($e->getMessage());
        $this->_responseFactory->create()->setRedirect($this->_storeManager->
            getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB))->sendResponse(); 
    }
    /**
     * Post a request and retrieve decoded data 
     *
     * @param  $url
     * @param  $data
     * @return string
     */  
    public function getCurlReqData($url,$data)
    {
        $this->setCurlOptions();
        $this->_curlClient->setOption(CURLOPT_RETURNTRANSFER, true);
        parse_str($data, $params);
        $params = $this->replaceArrayKeys($params);
        $this->_curlClient->post($url, $params);
        $response = $this->_curlClient->getBody();
        $decodedData = $this->_jsonHelper->jsonDecode($response);

        return $decodedData;
    }
    /**
     * Post a request and retrieve decoded data 
     *
     * @param  $url
     * @return string
     */  
    public function getCurlRespData($url)
    {
        $this->setCurlOptions();
        $this->_curlClient->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curlClient->get($url);
        $response = $this->_curlClient->getBody();
        $decodedData = $this->_jsonHelper->jsonDecode($response);
        return $decodedData;
    }
    /**
     * Replace _ char with . char
     *
     * @param  $array
     * @return array
     */
    private function replaceArrayKeys( $array ) {

        $replacedKeys = str_replace('_', '.', array_keys($array));
        return array_combine($replacedKeys, $array);
    }
    public function getPaymentMarkImageUrl($code)
    {
        $paymentImage = '';
        switch ($code) {
            case 'HyperPay_Visa':
                $paymentImage =$this->_assetRepo->getUrl("Hyperpay_Extension::images/visa.svg");
                break;
            case 'HyperPay_Mada':
                $paymentImage =$this->_assetRepo->getUrl("Hyperpay_Extension::images/mada.svg");;
                break;
            case 'HyperPay_SadadNcb':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/sadad.png");;
                break;
            case 'HyperPay_PayPal':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/paypal.svg");;
                break;
            case 'HyperPay_Master':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/master.svg");;
                break;
            case 'HyperPay_Amex':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/amex.svg");;
                break;
            case 'HyperPay_SadadPayware':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/sadad.png");;
                break;
            case 'HyperPay_ApplePay':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/apple.svg");;
                break;
            case 'HyperPay_stc':
                $paymentImage= $this->_assetRepo->getUrl("Hyperpay_Extension::images/stc.png");;
                break;

        }
        return $paymentImage;
    }
}
