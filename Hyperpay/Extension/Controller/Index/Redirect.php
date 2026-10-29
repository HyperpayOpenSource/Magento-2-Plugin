<?php

namespace Hyperpay\Extension\Controller\Index;

/**
 * Create a server-to-server payment and redirect the shopper to the gateway
 */
class Redirect extends \Magento\Framework\App\Action\Action
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
     *
     * @var \Hyperpay\Extension\Model\ServerToServer
     */
    protected $_serverToServer;
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Magento\Framework\Registry                $coreRegistry
     * @param \Magento\Checkout\Model\Session            $checkoutSession
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Hyperpay\Extension\Model\Adapter          $adapter
     * @param \Hyperpay\Extension\Model\ServerToServer   $serverToServer
     * @param \Psr\Log\LoggerInterface                   $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context      $context,
        \Magento\Framework\Registry                $coreRegistry,
        \Magento\Checkout\Model\Session            $checkoutSession,
        \Magento\Framework\View\Result\PageFactory $pageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Hyperpay\Extension\Model\Adapter          $adapter,
        \Hyperpay\Extension\Model\ServerToServer   $serverToServer,
        \Psr\Log\LoggerInterface                   $logger
    )
    {
        parent::__construct($context);
        $this->_coreRegistry = $coreRegistry;
        $this->_checkoutSession = $checkoutSession;
        $this->_pageFactory = $pageFactory;
        $this->_storeManager = $storeManager;
        $this->_adapter = $adapter;
        $this->_serverToServer = $serverToServer;
        $this->_logger = $logger;
    }

    public function execute()
    {
        $order = $this->_checkoutSession->getLastRealOrderId() ? $this->_checkoutSession->getLastRealOrder() : null;
        if (!$order || !$order->getId()) {
            return $this->fail(__('Order is not found'));
        }
        if (($order->getState() !== 'new') && ($order->getState() !== 'pending_payment')) {
            return $this->fail(__("This order has already been processed,Please place a new order"));
        }
        $method = $order->getPayment()->getData('method');
        if (!$this->_adapter->isServerToServer($method)) {
            return $this->fail(__('Order is not found'));
        }

        try {
            $base = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $returnUrl = $base . 'hyperpay/index/result/?method=' . $method;
            $form = $this->_serverToServer->initiate($order, $returnUrl);
        } catch (\Exception $e) {
            $this->_logger->error('[HyperPay][s2s] payment request failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            return $this->fail($e->getMessage());
        }

        $this->_coreRegistry->register('hyperpay_redirect', $form);

        return $this->_pageFactory->create();
    }

    /**
     * Add an error message and redirect to the failure page
     *
     * @param  string $message
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function fail($message)
    {
        $this->messageManager->addErrorMessage($message);
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('checkout/onepage/failure');

        return $resultRedirect;
    }
}
