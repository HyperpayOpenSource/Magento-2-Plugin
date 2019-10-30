<?php
namespace Hyperpay\Extension\Controller\Index;



class Sadad extends \Magento\Framework\App\Action\Action
{

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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter
     */
    protected $_adapter;
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Magento\Framework\Registry                $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data               $helper
     * @param \Magento\Checkout\Model\Session            $checkoutSession
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Hyperpay\Extension\Model\Adapter             $adapter
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Hyperpay\Extension\Helper\Data $helper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Hyperpay\Extension\Model\Adapter $adapter
    ) 
    { 
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_helper=$helper;
        $this->_coreRegistry=$coreRegistry;
        $this->_pageFactory = $pageFactory;
        $this->_adapter=$adapter;
        $this->_storeManager = $storeManager;
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
            $this->_redirect($this->_storeManager->getStore()->
                getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB));
            return ;
        }

        $this->_adapter->setOrder($order);
        try{
            $stagingCheckoutUrl= $this->makeReqUsingSadad($order);
        }
        catch (\Exception $e)
        {
            $this->_helper->catchExceptionRedirectAndCancelOrder($order, $e);
            return $this->_pageFactory->create();
        }

        $this->_redirect($stagingCheckoutUrl);
    }

    /**
     * Build data for sadad method and make a request to gateway
     * and return url of redirect form 
     *
     * @param $order
     * @return string
     */ 
    public function makeReqUsingSadad($order)
    {
        
        $payment= $order->getPayment();
        $amount=$order->getBaseGrandTotal();
        $orderId = str_pad($order->getIncrementId(), 20, "0", STR_PAD_LEFT);
        $total=$this->_helper->convertPrice($payment, $amount);
        $serviceUrl = $this->_adapter->getSadadReqUrl();
        
        $reqArray = array("api_user_name"=>$this->_adapter->getApiUserName($payment), "api_secret"=>$this->_adapter->getApiSecret($payment), "merchant_id"=>$this->_adapter->getMerchantId($payment), "transaction_number"=>$orderId,"success_url"=>$this->_adapter->getSadadUrl(), "failure_url"=>$this->_adapter->getSadadUrl(),"lang"=>'EN',"is_testing"=>$this->_adapter->getEnv(),"amount"=>$total);
        $data = json_encode($reqArray);
        
        

        $this->_helper->setSadadHeaders($data);
        $decodedData = $this->_helper->getCurlReqData($serviceUrl, $data);
            // Redirect to PayWare checkout page
        $stagingCheckoutUrl = $this->_adapter->getSadadRedirectUrl() . $decodedData;
        return $stagingCheckoutUrl;

        
       
    }

}