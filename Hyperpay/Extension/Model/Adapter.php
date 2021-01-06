<?php
namespace Hyperpay\Extension\Model;

use \Magento\Sales\Model\Order as OrderStatus;

/**
 * Pay In Store payment method model
 */
class Adapter extends \Magento\Framework\Model\AbstractModel
{

    const ENTITY_ID = 'entityId';
    const TEST_URL = 'testurl';
    const LIVE_URL = 'liveurl';
    const MODE = 'mode';
    const STYLE = 'style';
    const ORDER_STATUS = 'order_status';
    const CONNECTOR = 'connector';
    const CSS = 'css';
    const PAYMENT_ACTION = 'payment_action';
    const CURRENCY_CODE ='currencycode';
    const API_USER_NAME = 'api_user_name';
    const API_SECRET = 'api_secret';
    const MERCHANT_ID = 'merchant_id';
    const RISK_CHANNEL_ID = 'riskChannelId';
    const ACCESS_TOKEN = 'auth';
    /**
     *  
     * @var string
     */
    protected $_sadadStatusTestUrl='https://stg.sadad.hyperpay.com/PayWareHub/api/PayWare/GetCheckoutStatus';
    /**
     *
     * @var string
     */
    protected $_sadadStatusLiveUrl='https://sadad.hyperpay.com/PayWareHub/api/PayWare/GetCheckoutStatus';
    /**
     *
     * @var string
     */
    protected $_sadadRequestTestUrl='https://stg.sadad.hyperpay.com/PayWareHub/api/PayWare/SetCheckout';
    /**
     *
     * @var string
     */
    protected $_sadadRequestLivetUrl='https://sadad.hyperpay.com/PayWareHub/api/PayWare/SetCheckout';
    /**
     *
     * @var string
     */
    protected $_sadadTestRedirectUrl="https://stg.sadad.hyperpay.com/PayWareHub/Pages/Checkout/Checkout.aspx?id=";
    /**
     *
     * @var string
     */
    protected $_sadadLiveRedirectUrl="https://sadad.hyperpay.com/PayWareHub/Pages/Checkout/Checkout.aspx?id=";
    /**
     *
     * @var string
     */
    protected $_storeScope= \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    /**
     *
     * @var string
     */
    protected $_status = 'fail';
    /**
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     *
     * @var \Magento\Framework\App\Request\Http
     */
    protected $_request;

    /**
     *
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $_invoiceCollectionFactory;

    /**
     *
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     *
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $_transactionFactory;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;
    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $_orderManagement;
    /**
     * @var \Magento\CatalogInventory\Api\StockRegistryInterface
     */
    protected $_stockRegistry;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $_productRepository;
    /**
     * @var  \Hyperpay\Extension\Model\Source\BlackBins
     */
    protected $blackBins;

    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context                                   $context
     * @param \Magento\Framework\Registry                                        $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface                 $scopeConfig
     * @param \Magento\Framework\Json\Helper\Data                                $jsonHelper
     * @param \Magento\Store\Model\StoreManagerInterface                         $storeManager
     * @param \Magento\Framework\App\Request\Http                                $request
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService                        $invoiceService
     * @param \Magento\Framework\DB\TransactionFactory                           $transactionFactory
     * @param \Magento\Framework\ObjectManagerInterface                          $objectManager
     * @param \Magento\Sales\Api\OrderManagementInterface                        $orderManagement
     * @param \Magento\Catalog\Model\ProductRepository                           $productRepository
     * @param \Hyperpay\Extension\Model\Source\BlackBins                         $blackBins
     * @param  \Magento\CatalogInventory\Api\StockRegistryInterface              $stockRegistry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource            $resource 
     * @param \Magento\Framework\Data\Collection\AbstractDb                      $resourceCollection
     * @param array                                                              $data
     */ 
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
	    \Magento\Catalog\Model\ProductRepository $productRepository,
        \Hyperpay\Extension\Model\Source\BlackBins $blackBins,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = array()
    ) 
    { 
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager=$storeManager;
        $this->blackBins=$blackBins;
        $this->_request = $request;
        $this->_objectManager = $objectManager;
        $this->_orderManagement = $orderManagement;
        $this->_invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->_invoiceService = $invoiceService;
        $this->_transactionFactory = $transactionFactory;
 	$this->_stockRegistry = $stockRegistry;
        $this->_productRepository = $productRepository;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Retrieve the Decrease Stock When Order is Placed option from configuration
     *
     * @return string
     */
    public function getStockOption()
    {
        return $this->_scopeConfig->getValue('cataloginventory/options/can_subtract', $this->_storeScope);
    }
    /**
     * Retrieve the server mode from configuration
     *
     * @return string
     */ 
    public function getMode()
    {
        return $this->getConfigData(self::MODE);
    }
    /**
     * Retrieve Access token from configuration
     *
     * @return string
     */
    public function getAccessToken()
    {
        return $this->getConfigData(self::ACCESS_TOKEN);
    }
    /**
     * Retrieve risk channel id from configuration
     *
     * @return string
     */ 
    public function getRiskChannelId()
    {
        return $this->getConfigData(self::RISK_CHANNEL_ID);
    }
    /**
     * Retrieve the style of payment form from configuration
     *  Options :
     *  card , none, plain
     *
     * @return string
     */ 
    public function getStyle()
    {
        return $this->getConfigData(self::STYLE);
    }
    /**
     * Retrieve the CSS tags and attributes of payment form from configuration
     *
     * @return string
     */ 
    public function getCss()
    {
        return $this->getConfigData(self::CSS);

    }
    /**
     * Retrieve the Url depending on environment 'server mode' from configuration
     *
     * Options :
     * Integrator Test , Connector Test, Live
     *
     * @return string
     */ 
    public function getUrl()
    {

        if ($this->getMode() == "live") {
            return $this->getConfigData(self::LIVE_URL);
        }
        else
        {
            return $this->getConfigData(self::TEST_URL);
        }
    }
    /**
     * Retrieve the Connector from configuration
     *
     * Options :
     * MIGS , Visa ACP
     *
     * @return string
     */
    public function getConnector($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::CONNECTOR);
    }
    /**
     * Retrieve the entity id from configuration
     *
     * @param  $payment
     * @return string
     */
    public function getEntity($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::ENTITY_ID);

    }
    /**
     * Retrieve the payment type depending on method code from configuration
     *
     * @param  $payment
     * @return string
     */
    public function getPaymentType($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::PAYMENT_ACTION);
    }
    /**
     * Retrieve the currency code depending on method code from configuration
     *
     * @param  $payment
     * @return string
     */
    public function getSupportedCurrencyCode($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::CURRENCY_CODE);
    }
    /**
     * Retrieve the status from configuration
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getConfigData(self::ORDER_STATUS);

    }
    /**
     * Retrieve the Api User Name for sadad depending on method code from configuration
     *
     * @param  $payment
     * @return string
     */
    public function getApiUserName($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::API_USER_NAME);
    }
    /**
     * Retrieve the api secret for sadad depending on method code from configuration
     *
     * @param  $payment
     * @return string
     */
    public function getApiSecret($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::API_SECRET);
    }
    /**
     * Retrieve the merchant id for sadad depending on method code from configuration
     *
     * @param  $payment
     * @return string
     */
    public function getMerchantId($method)
    {
        return $this->getConfigDataForSpecificMethod($method, self::MERCHANT_ID);
    }
    /**
     * Add mode to data of curl request depending on server mode
     *
     * @return string
     */
    public function getModeHyperpay()
    {
        if ($this->getMode() == "test") {
            return "&testMode=EXTERNAL"; 
        } 
    }
    /**
     * Retrieve false on live mode and false otherwise 
     *
     * @return boolean
     */
    public function getEnv()
    {
        if($this->getMode()=="live") {
            return false; 
        }

        return true;
    }
    /**
     * Retrieve sadad request url depending on server mode
     *
     * @return string
     */
    public function getSadadReqUrl()
    {
        if($this->getEnv()) {
            return $this->_sadadRequestTestUrl; 
        }

          return $this->_sadadRequestLivetUrl;

    }
    /**
     * Retrieve sadad redirect url depending on server mode
     *
     * @return string
     */
    public function getSadadRedirectUrl()
    {
        if($this->getEnv()) {
            return $this->_sadadTestRedirectUrl; 
        }

          return $this->_sadadLiveRedirectUrl;
    }
    /**
     * Retrieve sadad Status url depending on server mode
     *
     *  *on both successful and failed transaction  
     *
     * @return string
     */
    public function getSadadStatusUrl()
    {
        if($this->getEnv()) {
            return $this->_sadadStatusTestUrl; 
        }

          return $this->_sadadStatusLiveUrl;


    }
    /**
     * Retrieve url that redirect from checkout page
     *
     * @return string
     */
    public function getSadadUrl()
    {
        $base = $this->_storeManager->getStore()->
        getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        return $base."hyperpay/index/sstatus";
    }
    /**
     * Set status and state to database after transaction complete 
     * and return sucess or fail to view 
     *
     * @param  $$decodedData
     * @param  $order
     * @return string
     */
    public function orderStatus($decodedData,$order)
    {

        if (preg_match('/^(000\.400\.0|000\.400\.100)/', $decodedData['result']['code'])
            || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $decodedData['result']['code'])) {
            $order->addStatusHistoryComment($decodedData['result']['description'], false);
            $this->createInvoice($order);
            $this->_status = 'success';
        } else {
            $order->addStatusHistoryComment($decodedData['result']['description'], OrderStatus::STATE_CANCELED);
            $order->setState(OrderStatus::STATE_CANCELED);
            $orderCommentSender = $this->_objectManager
                ->create('Magento\Sales\Model\Order\Email\Sender\OrderCommentSender');
            $orderCommentSender->send($order, true, '');
            $order->save();
            if($order->getPayment()->getData('method')=='SadadNcb') {
                $this->_status = $decodedData['resultDetails']['ErrorMessage'];
            } else {
                $this->_status = $decodedData['result']['description'];
            }
            if (isset($decodedData['card']['bin'])) {
                $blackBins =$this->blackBins->bins();
                $searchBin = $decodedData['card']['bin'];
                if (in_array($searchBin,$blackBins)) {
                    $this->_status =__('Sorry! Please select mada payment option in order to be able to complete your purchase successfully.');

                }
            }
        }

        return $this->_status;
    }
    /**
     * Set status and state to database after transaction complete 
     * and return sucess or fail to view (Sadad payment method)
     *
     * @param  $$decodedData
     * @param  $order
     * @return string
     */
    public function orderStatusSadad($decodedData,$order)
    {
        if ($decodedData=="0") {
            $order->addStatusHistoryComment('Request successfully processed', $this->getStatus());
            $order->setState($this->getStatus());
            $this->_orderManagement->notify($order->getEntityId());
            $order->save();
            $this->createInvoice($order);
            $this->_status = 'success';
        } 
        else 
        {
            $errorMessage = $this->_request->getParam('ErrorDescription');
            $order->addStatusHistoryComment($errorMessage, 
                OrderStatus::STATE_CANCELED);
            $order->setState(OrderStatus::STATE_CANCELED);
            $orderCommentSender = $this->_objectManager
                ->create('Magento\Sales\Model\Order\Email\Sender\OrderCommentSender');
            $orderCommentSender->send($order, true, '');
            $order->save();
            $this->_status = $errorMessage;
        }

        return $this->_status;
    }
    /**
     * Set checkoutId to additionalInformation column in sales_order_payment table
     *
     * @param $order
     * @param $checkOutId  
     */
    public function setInfo($order, $checkOutId)
    {    
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('checkoutId', $checkOutId);
        $order->save();

    }
    /**
     * Get checkoutId from additionalInformation column in sales_order_payment table 
     *
     * @param  $payment
     * @return string 
     */
    public function getCheckoutId($payment)
    {
        return $payment->getAdditionalInformation('checkoutId');
    }
    /**
     * Set payment type and currency to additionalInformation column in sales_order_payment table 
     *
     * @param $order
     * @param $paymentType
     * @param $currency 
     */
    public function setPaymentTypeAndCurrency($order, $paymentType,$currency)
    {
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('payment_type', $paymentType);
        $payment->setAdditionalInformation('currency', $currency);
        $order->save();
   
    }
    /**
     * Retrieve configuration from admin panel for hyperpay group
     *
     * @param  $field 
     * @return string
     */
    public function getConfigData($field)
    {
        return $this->_scopeConfig->getValue('payment/hyperpay/'.$field, $this->_storeScope);

    }
    /**
     * Retrieve configuration from admin panel for specific payment method group
     *
     * @param  $payment 
     * @param  $field 
     * @return string
     */
    public function getConfigDataForSpecificMethod($method,$field)
    {
        return $this->_scopeConfig->getValue('payment/'.$method.'/'.$field, $this->_storeScope);

    }
    /**
     * Bulid data for capture curl request 
     *
     * @param  $payment
     * @param  $currency 
     * @param  $grandTotal 
     * @return string
     */
    public function buildCaptureOrRefundRequest($payment,$currency,$grandTotal,$op)
    {
        $data = "entityId=".$this->getEntity($payment->getData('method')).
            "&currency=".$currency.
            "&amount=".$grandTotal.
            "&paymentType=".$op;
        $data .= $this->getModeHyperpay();
        return $data;
    }
    /**
     * Create invoice automatically
     * **status will be set to processing 
     * 
     * @param $$order 
     */
    public function createInvoice($order)
    {

        if(!$order->getId()) {
            $order->addStatusHistoryComment('The order id is not found',$this->getStatus());
            $order->setState(OrderStatus::STATE_PROCESSING)->setStatus($this->getStatus());
            $order->save();
            return $this;
        }

        try {
            $invoices = $this->_invoiceCollectionFactory->create()
                ->addAttributeToFilter('order_id', array('eq' => $order->getId()));

            $invoices->getSelect()->limit(1);

            if ((int)$invoices->count() !== 0) {
                $order->addStatusHistoryComment('The order has been invoiced already ',$this->getStatus());
                $order->setState(OrderStatus::STATE_PROCESSING)->setStatus($this->getStatus());
                $this->_orderManagement->notify($order->getEntityId());
                $order->setEmailSent(true);
                $order->save();
                return null;
            }

            if(!$order->canInvoice()) {
                $order->addStatusHistoryComment('Could not create an invoice,Creating invoices is inactive',$this->getStatus());
                $order->setState(OrderStatus::STATE_PROCESSING)->setStatus($this->getStatus());
                $this->_orderManagement->notify($order->getEntityId());
                $order->setEmailSent(true);
                $order->save();
                return null;
            }
            foreach ($order->getAllItems() as $item) {
                if($item->getProduct()->getIsVirtual())
                {
                    $order->addStatusHistoryComment('Could not create an invoice,The items has virtual product',$this->getStatus());
                    $order->setState(OrderStatus::STATE_PROCESSING)->setStatus($this->getStatus());
                    $this->_orderManagement->notify($order->getEntityId());
                    $order->setEmailSent(true);
                    $order->save();
                    return null;
                }
            }
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $code = $order->getPayment()->getData('method');
            if ($this->getPaymentType($code) == "DB") {
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            }
            else{
                $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::NOT_CAPTURE);
            }
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment('Automatically INVOICED', false);
            $transactionSave = $this->_transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();
            $order->addStatusHistoryComment('Request successfully processed', $this->getStatus());
            $order->setState(OrderStatus::STATE_PROCESSING)->setStatus($this->getStatus());
            $order->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(),
                false);
            $this->_orderManagement->notify($order->getEntityId());
            $order->setState(OrderStatus::STATE_PROCESSING)->setStatus($this->getStatus());
            $order->setEmailSent(true);
            $order->save();
            return null;
        }
        try {
            $this->_orderManagement->notify($order->getEntityId());
            $invoice->getOrder()->setCustomerNoteNotify(true);
            $order->setEmailSent(true);
            $order->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(),false);
            $order->save();
            return null;
        }
    }
}
