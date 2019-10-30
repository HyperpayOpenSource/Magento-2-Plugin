<?php
namespace Hyperpay\Extension\Controller\Index;

 use \Magento\Sales\Model\Order as OrderStatus;


class Status extends \Magento\Framework\App\Action\Action
{
    /**
     *
     * @var \Magento\Framework\View\Result\PageFactory 
     */
    protected $_pageFactory;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter 
     */
    protected $_adapter;
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
     * @var \Magento\Framework\App\Request\Http
     */
    protected $_request;
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
     * Constructor
     * 
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Hyperpay\Extension\Model\Adapter             $adapter
     * @param \Magento\Framework\Registry                $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data               $helper
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Framework\App\Request\Http        $request
     * @param \Magento\Checkout\Model\Session            $checkoutSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Magento\Framework\Registry $coreRegistry,
        \Hyperpay\Extension\Helper\Data $helper,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) 
    { 
        parent::__construct($context);
        $this->_pageFactory = $pageFactory;
        $this->_coreRegistry=$coreRegistry;
        $this->_checkoutSession = $checkoutSession;
        $this->_request = $request;
        $this->_helper=$helper;
        $this->_storeManager=$storeManager;
        $this->_adapter=$adapter;
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
        
        try{
            $data= $this->getHyperpayStatus($order);
            $status = $this->_adapter->orderStatus($data, $order);
            $this->_coreRegistry->register('status', $status);
        }catch(\Exception $e)
        {
            $order->setState(OrderStatus::STATE_HOLDED);
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), OrderStatus::STATE_HOLDED);
            $order->save();
            $this->messageManager->addError($e->getMessage());
            $this->_pageFactory->create();
        }

        
        return $this->_pageFactory->create();

    }
    /**
     * Retrieve payment gateway response and set id to payment table
     *
     * @param $order
     * @return string
     */ 
    public function getHyperpayStatus($order)
    {
       $payment= $order->getPayment();
        if(empty($this->_request->getParam('id'))) {
            $this->_helper->doError('Checkout id does not found');
        }

        $id = $this->_request->getParam('id');
        $url = $this->_adapter->getUrl()."checkouts/".$id."/payment";
        $url .= "?authentication.entityId=".$this->_adapter->getEntity($payment);
        $auth = array('Authorization'=>'Bearer '.$this->_adapter->getAccessToken());
        $this->_helper->setHeaders($auth);
        $decodedData = $this->_helper->getCurlRespData($url);
        
        if (!isset($decodedData)) {
            $this->_helper->doError('No response data found');
        }
        if (!isset($decodedData['id'])) {
            $this->_helper->doError('Failed to get response from the payment gateway,Please check your request data and url');
        }
        $this->_adapter->setInfo($order, $decodedData['id']);
          
        
        return $decodedData;
        
    }


}
