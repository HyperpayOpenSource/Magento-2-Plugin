<?php
namespace Hyperpay\Extension\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order as OrderStatus;

/**
 * Class CancelOrderPending
 */
class CancelOrderPending
{

    protected $_orderCollectionFactory;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter
     */
    protected $_adapter;
    private $logger;
    protected $_scopeConfig;
    protected $_storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    protected $_stdTimezone;
    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    protected $_orderManagement;
    /**
     *
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $_jsonHelper;

    /**
     * CancelOrderPending constructor.
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Hyperpay\Extension\Model\Adapter $adapter
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Psr\Log\LoggerInterface                                   $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface         $scopeConfig,
        \Hyperpay\Extension\Model\Adapter                          $adapter,
        \Magento\Framework\Json\Helper\Data                        $jsonHelper,
        \Magento\Sales\Api\OrderManagementInterface                $orderManagement,
        \Magento\Framework\Stdlib\DateTime\Timezone                $stdTimezone
    )
    {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->_adapter = $adapter;
        $this->_scopeConfig = $scopeConfig;
        $this->_stdTimezone = $stdTimezone;
        $this->_jsonHelper = $jsonHelper;
        $this->_orderManagement = $orderManagement;

    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        $time = $this->_scopeConfig->getValue('payment/hyperpay/cancel_order', $this->_storeScope);
        if ($time < 30) {
            $time = 30;
        }
        $methods = [
            'HyperPay_Amex',
            'HyperPay_Mada',
            'HyperPay_Master',
            'HyperPay_PayPal',
            'HyperPay_Visa',
            'HyperPay_ApplePay',
            'HyperPay_stc'
        ];
        $activeMethods = [];
        foreach ($methods as $method) {
            if ($this->_scopeConfig->getValue('payment/' . $method . '/cron_cancel', $this->_storeScope)) {
                array_push($activeMethods, $method);
            }

        }
        if (!empty($activeMethods)) {
            $time = $time * 60;
            $currentTime = $this->_stdTimezone->date((time() - $time))->format('Y-m-d H:i:s');

            $this->logger->info($currentTime);
            try {
                $orders = $this->_orderCollectionFactory->create()
                    ->addFieldToFilter('updated_at', ['to' => $currentTime])
                    ->addFieldToFilter('state', array('in' => ['new', 'pending_payment']));
                $orders->getSelect()
                    ->join(
                        ["sop" => "sales_order_payment"],
                        'main_table.entity_id = sop.parent_id',
                        array('method')
                    )
                    ->where('sop.method IN (?)', $activeMethods);


                $order_ids = [];
                $baseUrl = $this->_adapter->getUrl();
                foreach ($orders as $order) {

                    $payment = $order->getPayment();
                    $method = $payment->getData('method');
                    $accesstoken = $this->_adapter->getAccessToken();
                    $entityId = $this->_adapter->getEntity($method);
                    $orderId = $order->getIncrementId();
                    $url = $baseUrl . "query";
                    $url .= "?entityId=" . $entityId;
                    $url .= "&merchantTransactionId=" . $orderId;
                    $this->logger->info($url);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:Bearer ' . $accesstoken));
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// this should be set to true in production
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $responseData = curl_exec($ch);
                    if (curl_errno($ch)) {
                        continue;
                    }
                    curl_close($ch);
                    $this->logger->info($responseData);
                    $decodedData = $this->_jsonHelper->jsonDecode($responseData);
                    $order_ids[] = $order->getIncrementId();
                    if ($decodedData['result']['code'] === "700.400.580") {
                        $order->addStatusHistoryComment('Order has been canceled automatically, ', \Magento\Sales\Model\Order::STATE_CANCELED);
                        $this->_orderManagement->cancel($order->getEntityId());
                    } else {
                        $orderTime = new \DateTime($order->getCreatedAt());
                        $status = false;
                        foreach ($decodedData['payments'] as $payment) {
                            $paymentTime = new \DateTime($payment['timestamp']);
                            $interval = date_diff($paymentTime, $orderTime);
                            $diffDays = $interval->format('%a');
                            if ($diffDays <= 1) {
                                if (preg_match('/^(000\.400\.0|000\.400\.100)/', $payment['result']['code'])
                                    || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $payment['result']['code'])) {
                                    $order->addStatusHistoryComment($payment['result']['description'], false);
                                    $order->addStatusHistoryComment('Order has been updated automatically,status: success', false);
                                    $this->_adapter->createInvoice($order);
                                    $status = true;
                                    $this->_adapter->setInfo($order, $payment['id']);

                                }
                            }
                        }
                        if (!$status) {
                            $order->addStatusHistoryComment('Order has been canceled automatically, ', \Magento\Sales\Model\Order::STATE_CANCELED);
                            $order->setState(OrderStatus::STATE_CANCELED);
                            $this->_orderManagement->cancel($order->getEntityId());
                        }
                    }
                }
                $this->logger->info('cancelled orders ' . implode(' , ', $order_ids));
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

    }
}
