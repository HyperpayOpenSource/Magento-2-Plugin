<?php
namespace Hyperpay\Extension\Controller\Index;



class Request extends \Magento\Framework\App\Action\Action
{
    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     *
     * @var \Magento\Framework\View\Result\PageFactory 
     */
    protected $_pageFactory;
    /**
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;
    /**
     *
     * @var \Hyperpay\Extension\Helper\Data
     */
    protected $_helper;
    /**
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;
     /**
     *
     * @var \Hyperpay\Extension\Model\Adapter
     */
    protected $_adapter;
     /**
     *
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    protected $_remote;
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\Action\Context                $context
     * @param \Magento\Framework\Registry                          $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data                         $helper
     * @param \Magento\Checkout\Model\Session                      $checkoutSession
     * @param \Magento\Framework\View\Result\PageFactory           $pageFactory
     * @param \Magento\Store\Model\StoreManagerInterface           $storeManager
     * @param \Hyperpay\Extension\Model\Adapter                       $adapter
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remote
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Hyperpay\Extension\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remote
    ) 
    { 
        $this->_coreRegistry=$coreRegistry;
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_helper=$helper;
        $this->_pageFactory = $pageFactory;
        $this->_adapter=$adapter;
        $this->_storeManager = $storeManager;
        $this->_remote=$remote;

    }
    public function execute()
    {
        try {
            if(!($this->_checkoutSession->getLastRealOrderId())) {
                $this->_helper->doError('Order id does not found');
            }   
       
            $order=$this->_checkoutSession->getLastRealOrder();
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            return $this->_pageFactory->create();
        }
        
        if($order->getStatus() != 'pending') {
            $this->_redirect($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB));
            return ;
        }
         
        $this->_adapter->setOrder($order);
        try 
        {
            $urlReq=$this->prepareTheCheckout($order);
        }
        catch (\Exception $e)
        {
            $this->messageManager->addError($e->getMessage());
            return $this->_pageFactory->create();
        }

        $this->_coreRegistry->register('formurl', $urlReq);

        return $this->_pageFactory->create();
    }
    /**
     * Build data and make a request to hyperpay payment gateway
     * and return url of form 
     *
     * @param $order
     * @return string
     */ 
    public function prepareTheCheckout($order)
    {

        $payment= $order->getPayment();
        
        
        //$shippingMethod =$order->getShippingMethod();
        $email = $order->getBillingAddress()->getEmail();
        //order# 
        $orderId=$order->getIncrementId();
        $amount=$order->getBaseGrandTotal();
        $total=$this->_helper->convertPrice($payment, $amount);

        if($this->_adapter->getEnv()) {
            $grandTotal = (int) $total;

        }else {
            $grandTotal=number_format($total, 2, '.', '');
        }

        $currency=$this->_adapter->getSupportedCurrencyCode($payment);
        $paymentType =$this->_adapter->getPaymentType($payment);
        $this->_adapter->setPaymentTypeAndCurrency($order, $paymentType, $currency);

        $ip = $this->_remote->getRemoteAddress();
        $url = $this->_adapter->getUrl().'checkouts';
        $data = "entityId=".$this->_adapter->getEntity($payment).
        "&amount=".$grandTotal.
        "&currency=".$currency.
        "&paymentType=".$paymentType. 
        "&customer.ip=".$ip.
        "&customer.email=".$email.
        "&shipping.customer.email=".$email.
        "&merchantTransactionId=".$orderId; 
        $auth = array('Authorization'=>'Bearer '.$this->_adapter->getAccessToken());
        $this->_helper->setHeaders($auth);
        $data .= $this->_helper->getBillingAndShippingAddress($order);
        if(!empty($this->_adapter->getRiskChannelId())) {
            $data .= "&risk.channelId=".$this->_adapter->getRiskChannelId(). 
                    "&risk.serviceId=I".
                    "&risk.amount=".$grandTotal.
                    "&risk.parameters[USER_DATA1]=Mobile";
        }
        
             
        
        $data .= $this->_adapter->getModeHyperpay();
        /*   .
        "&shipping.method=".$shippingMethod*/
        if($payment->getData('method')=='SadadNcb') {
            $data .="&bankAccount.country=SA"; 
        }
        if($this->_adapter->getEnv() && $payment->getData('method')=='HyperPay_ApplePay') {
            $data .= "&customParameters[3Dsimulator.forceEnrolled]=true";
        }
        $decodedData = $this->_helper->getCurlReqData($url, $data);
        if (!isset($decodedData['id'])) {
            $this->_helper->doError('Failed to get response from the payment gateway,Please check your request data and url');
        }
        return $this->_adapter->getUrl()."paymentWidgets.js?checkoutId=".$decodedData['id'];

        
    }

    
}
