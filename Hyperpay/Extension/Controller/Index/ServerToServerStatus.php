<?php

namespace Hyperpay\Extension\Controller\Index;

use Hyperpay\Extension\Model\Adapter;
use \Magento\Sales\Model\Order as OrderStatus;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ServerToServerStatus extends \Magento\Framework\App\Action\Action
{

    protected $_scopeConfig;

    protected $_storeScope = ScopeInterface::SCOPE_STORE;

    /**
     *
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_pageFactory;
    /**
     *
     * @var Adapter
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
     * @param \Magento\Framework\App\Action\Context $context
     * @param Adapter $adapter
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data $helper
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Magento\Sales\Model\OrderFactory $orderFactory ,
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context      $context,
        Adapter     $adapter,
        \Magento\Framework\Registry                $coreRegistry,
        \Hyperpay\Extension\Helper\Data       $helper,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Framework\App\Request\Http        $request,
        \Magento\Sales\Model\OrderFactory          $orderFactory,
        ScopeConfigInterface                       $scopeConfig

    )
    {
        parent::__construct($context);
        $this->_pageFactory = $pageFactory;
        $this->_coreRegistry = $coreRegistry;
        $this->_orderFactory = $orderFactory;
        $this->_request = $request;
        $this->_helper = $helper;
        $this->_adapter = $adapter;
        $this->_scopeConfig = $scopeConfig;

    }

    public function execute()
    {
//        die("i am here in ServerToServer.php");
        try {
            $data = $this->getStatusRequest();

            $order = $this->_orderFactory->create()->loadByIncrementId($data['merchantTransactionId']);
            if (!$order) {
                $this->_helper->doError(__('Order id does not found'));
            }
        } catch (\Exception $exception) {
            $this->messageManager->addError($exception->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }

        try {
            if ($order->getState() == 'processing') {
                $this->_redirect('checkout/onepage/success');
            }
            $this->_adapter->setInfo($order, $data['id']);
            $status = $this->_adapter->orderStatus($data, $order);
            $this->_coreRegistry->register('status', $status);
            if ($status !== 'success') {
                $this->messageManager->addError($status);
                $this->_redirect('checkout/onepage/failure');
            } else {
                $this->_redirect('checkout/onepage/success');

            }
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: ' . $e->getMessage(), false);
            $this->messageManager->addError($e->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }
    }

    /**
     * Retrieve payment gateway response and set id to payment table
     * @return array
     */

    private function getStatusRequest()
    {
        if (empty($this->_request->getParam('id'))) {
            $this->_helper->doError(__('Checkout id does not found'));
        }
        $id = $this->_request->getParam('id');

        $method = $this->_request->getParam('method');
        $entityId = $this->_adapter->getEntity($method);

        $baseUrl = $this->_adapter->getUrl();
        $url = $baseUrl . 'payments/' . $id;
        $url .= "?entityId=$entityId";
        $accessToken = $this->_adapter->getAccessToken();

        $auth = array('Authorization' => 'Bearer ' . $accessToken);
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
