<?php
namespace Hyperpay\Extension\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Class CancelOrderPending
 */
class CancelOrderPending
{

    protected $_orderCollectionFactory;

    private $logger;
    protected  $_scopeConfig;
    protected $_storeScope= \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
    protected $_stdTimezone;

    /**
     * CancelOrderPending constructor.
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param  \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\DateTime\Timezone $stdTimezone
    ) {
       $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->_scopeConfig = $scopeConfig;
        $this->_stdTimezone = $stdTimezone;

    }
    /**
     * @throws \Exception
     */
    public function execute()
    {
        $time = $this->_scopeConfig->getValue('payment/hyperpay/cancel_order', $this->_storeScope);
        if($time<30)
        {
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
        foreach ($methods as $method)
        {
            if($this->_scopeConfig->getValue('payment/'.$method.'/cron_cancel', $this->_storeScope))
            {
                array_push($activeMethods,$method);
            }

        }
        if (!empty($activeMethods)) {
            $time = $time*60;
            $currentTime = $this->_stdTimezone->date((time()-$time))->format('Y-m-d H:i:s');

            $this->logger->info($currentTime);
        try {
            $orders = $this->_orderCollectionFactory->create()
                ->addFieldToFilter('updated_at', ['to' => $currentTime])
                ->addFieldToFilter('state', array('in' => ['new','pending_payment']));
            $orders->getSelect()
                ->join(
                    ["sop" => "sales_order_payment"],
                    'main_table.entity_id = sop.parent_id',
                    array('method')
                )
                ->where('sop.method IN (?)', $activeMethods);


            $order_ids = [];

            foreach ($orders as $order) {
                $order_ids[] = $order->getId();
                $order->addStatusHistoryComment('Order has been canceled automatically', \Magento\Sales\Model\Order::STATE_CANCELED);
                $order->cancel();
                $order->save();
            }
            $this->logger->info('cancelled orders ' . implode(' , ', $order_ids));
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    }
}