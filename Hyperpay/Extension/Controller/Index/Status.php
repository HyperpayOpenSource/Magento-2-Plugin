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
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Hyperpay\Extension\Model\Adapter             $adapter
     * @param \Magento\Framework\Registry                $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data               $helper
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Framework\App\Request\Http        $request
     * @param \Magento\Sales\Model\OrderFactory $orderFactory,
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Magento\Framework\Registry $coreRegistry,
        \Hyperpay\Extension\Helper\Data $helper,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Sales\Model\OrderFactory $orderFactory

    ) 
    { 
        parent::__construct($context);
        $this->_pageFactory = $pageFactory;
        $this->_coreRegistry=$coreRegistry;
        $this->_orderFactory = $orderFactory;
        $this->_request = $request;
        $this->_helper=$helper;
        $this->_adapter=$adapter;
    }
    public function execute()
    {
        try{
            $data= $this->getHyperpayStatus();
            $order = $this->_orderFactory->create()->loadByIncrementId($data['merchantTransactionId']);
            if(!$order) {
                $this->_helper->doError(__('Order id does not found'));
            }
        }catch (\Exception $exception)
        {
            $this->messageManager->addError($exception->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }
        
        try{
            if(($order->getState() !== 'new') && ($order->getState() !== 'pending_payment')) {
                $this->messageManager->addError(__("This order has already been processed,Please place a new order"));
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('checkout/onepage/failure');
                return $resultRedirect;
            }
            $this->_adapter->setInfo($order, $data['id']);
            $status = $this->_adapter->orderStatus($data, $order);
            $this->_coreRegistry->register('status', $status);
            if ($status !== 'success')
            {
                $this->messageManager->addError($status);
                $this->_redirect('checkout/onepage/failure');

            }else{
                $this->_redirect('checkout/onepage/success');

            }
        }catch(\Exception $e)
        {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
            $this->messageManager->addError($e->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }
    }
    /**
     * Retrieve payment gateway response and set id to payment table
     *
     * @param $order
     * @return string
     */ 
    public function getHyperpayStatus()
    {
        if(empty($this->_request->getParam('id'))) {
            $this->_helper->doError(__('Checkout id does not found'));
        }

        $id = $this->_request->getParam('id');
        $url = $this->_adapter->getUrl()."checkouts/".$id."/payment";
        $url .= "?entityId=".$this->_adapter->getEntity($this->_request->getParam('method'));
        $auth = array('Authorization'=>'Bearer '.$this->_adapter->getAccessToken());
        $this->_helper->setHeaders($auth);
        $decodedData = $this->_helper->getCurlRespData($url);
        
        if (!isset($decodedData)) {
            $this->_helper->doError(__('No response data found'));
        }
        if (!isset($decodedData['id'])) {
            $this->_helper->doError(__('Failed to get response from the payment gateway'));
        }
        return $decodedData;
        
    }


}
