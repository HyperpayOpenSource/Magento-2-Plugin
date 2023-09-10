<?php
namespace Hyperpay\Extension\Controller\Index;

use \Magento\Sales\Model\Order as OrderStatus;


class Sstatus extends \Magento\Framework\App\Action\Action
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
        $this->_helper=$helper;
        $this->_adapter=$adapter;
        $this->_checkoutSession = $checkoutSession;
        $this->_request = $request;
        $this->_storeManager=$storeManager;


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

        $payment= $order->getPayment();
        if($order->getStatus() != 'pending') {
            $this->_redirect($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB));
            return ;
        }

        try
        {
            $data= $this->getSadadStatus($order);
            $status = $this->_adapter->orderStatusSadad($data, $order);
            $this->_coreRegistry->register('status', $status);
            return $this->_pageFactory->create();
        }
        catch(\Exception $e)
        {
            $this->_helper->catchExceptionRedirectAndCancelOrder($order, $e);
            return $this->_pageFactory->create();
        }


    }
    /**
     * Retrieve payment gateway of sadad payment method response and set id to payment table
     *
     * @param $order
     * @return string
     */
    public function getSadadStatus($order)
    {
        $serviceUrl = $this->_adapter->getSadadStatusUrl();

        if(empty($this->_request->getParam('MerchantRefNum'))) {
            $this->_helper->doError('Merchant Reference Number does not found');
        }

        $merchantRefNum = $this->_request->getParam('MerchantRefNum');

        $this->_adapter->setInfo($order, $merchantRefNum);
        $merchantId =$this->_adapter->getMerchantId($order->getPayment());
        $reqArray = array("transaction_no"=>$merchantRefNum, "merchant_id"=>$merchantId);
        $data = json_encode($reqArray);
        $this->_helper->setSadadHeaders($data);
        $decodedData = $this->_helper->getCurlReqData($serviceUrl, $data);
        return $decodedData;
    }

}