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

    /**
     * CancelOrderPending constructor.
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param  \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
       $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        $today = date("Y-m-d h:i:s");
        $to = strtotime('-40 min', strtotime($today));
        $to = date('Y-m-d h:i:s', $to);
        $methods = ['HyperPay_Amex',
            'HyperPay_Mada',
            'HyperPay_Master',
            'HyperPay_PayPal',
            'HyperPay_Visa',
            'HyperPay_ApplePay',
            'HyperPay_stc'
        ];
        try {
            $orders = $this->_orderCollectionFactory->create()
                ->addFieldToFilter('updated_at', ['to' => $to])
                ->addFieldToFilter('state', array('in' => ['new']));
            $orders->getSelect()
                ->join(
                    ["sop" => "sales_order_payment"],
                    'main_table.entity_id = sop.parent_id',
                    array('method')
                )
                ->where('sop.method IN (?)',$methods);


            $order_ids = [];

            foreach($orders as $order){
                $order_ids[] = $order->getId();
                $order->addStatusHistoryComment('Order has been canceled automatically', \Magento\Sales\Model\Order::STATE_CANCELED);
                $order->cancel();
                $order->save();
            }
            $this->logger->info('cancelled orders '. implode(' , ', $order_ids));
        } catch (\Exception $exception)
        {
            $this->logger->error($exception->getMessage());
        }


    }
}