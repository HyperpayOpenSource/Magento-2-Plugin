<?php

namespace Hyperpay\Extension\Controller\Index;


use Magento\Framework\App\ObjectManager;

class ServerToServer extends \Magento\Framework\App\Action\Action
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
     * @var \Magento\CatalogInventory\Api\StockManagementInterface
     */
    protected $_stockManagement;
    /**
     *
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $_resolver;
    protected $_quoteFactory;

    /**
     *
     * @var string
     */
    protected $_storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data $helper
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\Resolver $resolver
     * @param \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement
     * @param \Hyperpay\Extension\Model\Adapter $adapter
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remote
     */
    public function __construct(
        \Magento\Framework\App\Action\Context                  $context,
        \Magento\Framework\Registry                            $coreRegistry,
        \Hyperpay\Extension\Helper\Data                        $helper,
        \Magento\Checkout\Model\Session                        $checkoutSession,
        \Magento\Framework\View\Result\PageFactory             $pageFactory,
        \Magento\Store\Model\StoreManagerInterface             $storeManager,
        \Magento\Framework\Locale\Resolver                     $resolver,
        \Hyperpay\Extension\Model\Adapter                      $adapter,
        \Magento\Quote\Model\QuoteFactory                      $quoteFactory,
        \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress   $remote
    )
    {
        $this->_coreRegistry = $coreRegistry;
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_helper = $helper;
        $this->_pageFactory = $pageFactory;
        $this->_adapter = $adapter;
        $this->_storeManager = $storeManager;
        $this->_resolver = $resolver;
        $this->_remote = $remote;
        $this->_stockManagement = $stockManagement;
        $this->_quoteFactory = $quoteFactory;

    }

    public function execute()
    {
        try {
            if (!($this->_checkoutSession->getLastRealOrderId())) {
                $this->_helper->doError(__('Order is not found'));
            }

            $order = $this->_checkoutSession->getLastRealOrder();
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            return $this->_pageFactory->create();
        }
        $quote = $this->_quoteFactory->create()->load($order->getQuoteId());
        $quote->setIsActive(true);
        $quote->save();
        $this->_checkoutSession->replaceQuote($quote);
        if (($order->getState() !== 'new') && ($order->getState() !== 'pending_payment')) {
            $this->messageManager->addError(__("This order has already been processed,Please place a new order"));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }
        try {
            $base = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            $statusUrl = $base . "hyperpay/index/servertoserverstatus/?method=" . $order->getPayment()->getData('method');
            $urlReq = $this->serverToServer($order, $statusUrl);

        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        }

        $this->_coreRegistry->register('formurl', $urlReq);
        $this->_coreRegistry->register('status', $statusUrl);

        return $this->_pageFactory->create();
    }

    /**
     * Build data and make a request to hyperpay payment gateway
     * and return url of form
     *
     * @param $order
     * @return string
     */
    public function serverToServer($order, $status)
    {
        $payment = $order->getPayment();
        $method = $payment->getData('method');
        $email = $order->getBillingAddress()->getEmail();
        //order#
        $orderId = $order->getIncrementId();
        $amount = $order->getBaseGrandTotal();
        $total = $this->_helper->convertPrice($payment, $amount);

        if ($this->_adapter->getEnv()) {
            $grandTotal = (int)$total;
        } else {
            $grandTotal = number_format($total, 2, '.', '');
        }

        $currency = $this->_adapter->getSupportedCurrencyCode($method);
        $paymentType = $this->_adapter->getPaymentType($method);
        $this->_adapter->setPaymentTypeAndCurrency($order, $paymentType, $currency);
        $entityId = $this->_adapter->getEntity($method);
        $baseUrl = $this->_adapter->getServerToServerUrl();
        $data = "entityId=" . $entityId .
            "&notificationUrl=" . $status .
            "&shopperResultUrl=" . $status .
            "&amount=" . $grandTotal .
            "&paymentBrand=ZOODPAY" .
            "&currency=" . $currency .
            "&paymentType=" . $paymentType .
            "&customer.email=" . $email .
            "&testMode=EXTERNAL" .
            "&merchantTransactionId=" . $orderId .
            "&customParameters[service_code]=ZPI"; // fixed

        $accesstoken = $this->_adapter->getAccessToken();
        $auth = array('Authorization' => 'Bearer ' . $accesstoken);
        $this->_helper->setHeaders($auth);

        $data .= $this->_helper->getBillingAndShippingAddress($order);
        $data .= $this->buildCartItems();

        $decodedData = $this->_helper->getCurlServerToServer($baseUrl, $data);
        if (!isset($decodedData['id'])) {
            $this->_helper->doError(__('Request id is not found'));
            return;
        }

        if (!isset($decodedData['result']['code']) || $decodedData['result']['code'] != '000.200.000') {
            $desc = \Safe\json_decode($decodedData['resultDetails']['ExtendedDescription'],true);
            $errors = '';
            if (isset($desc['details'])) {
                foreach ($desc['details'] as $detail){
                    $errors .= $detail['error'] . ' - ';
                }
            }
            $this->_helper->doError(__($errors));
            return;
        }

        $redirectForm = $this->buildRedirectForm($decodedData);
        if (!$redirectForm) {
            $this->_helper->doError(__($decodedData['result']['description']));
            return;
        }

        echo $redirectForm;
    }

    private function buildCartItems()
    {
        $objectManager = ObjectManager::getInstance();
        $cart = $objectManager->get('\Magento\Checkout\Model\Cart');
        // retrieve quote items array
        $items = $cart->getQuote()->getAllItems();
        $cartData = '';
        $categories = [];

        foreach ($items as $key => $item) {
            $cartData .= "&cart.items[" . $key . "].name=" . $item->getName() .
                "&cart.items[" . $key . "].price=" . number_format($item->getPrice(), 2, '.', '') .
                "&cart.items[" . $key . "].quantity=" . $item->getQty();
//                "&cart.items[" . $key . "].description=" . $item->getName() .
//                "&cart.items[" . $key . "].giftMessage=" . $item->getName();

            $categories[] = [["test"]];
        }
        $cartData .= "&customParameters['categories']=" . (json_encode($categories));

        return $cartData;
    }

    private function buildRedirectForm($data)
    {
        if (!isset($data['redirect'])) {
            return false;
        }

        $form = '<form id="redForm" action="' . $data['redirect']['url'] . '" method="POST">';
        foreach ($data['redirect']['parameters'] as $param) {
            $form .= '<input hidden name="' . $param['name'] . '" value="' . $param['value'] . '"/>';
        }
        $form .= '</form>';
        $form .= '<script> document.getElementById("redForm").submit();  </script>';
        return $form;
    }
}
