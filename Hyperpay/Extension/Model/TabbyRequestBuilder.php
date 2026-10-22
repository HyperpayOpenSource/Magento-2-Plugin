<?php

namespace Hyperpay\Extension\Model;

use Magento\Sales\Model\Order;

/**
 * Builds the Tabby specific server-to-server parameters
 */
class TabbyRequestBuilder
{
    const TEST_EMAIL = 'card.success@tabby.ai';
    const TEST_MOBILE = '0500000001';
    const HISTORY_LIMIT = 10;
    const DESCRIPTION_LENGTH = 255;
    const DATE_TIME_FORMAT = 'Y-m-d\TH:i:s\Z';
    /**
     *
     * @var string[]
     */
    protected $_paidStates = [
        Order::STATE_PROCESSING,
        Order::STATE_COMPLETE,
        Order::STATE_CLOSED
    ];
    /**
     *
     * @var \Hyperpay\Extension\Helper\Data
     */
    protected $_helper;
    /**
     *
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $_orderCollectionFactory;
    /**
     *
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $_customerRepository;
    /**
     *
     * @var \Magento\Catalog\Helper\Image
     */
    protected $_imageHelper;
    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     *
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * Constructor
     *
     * @param \Hyperpay\Extension\Helper\Data                            $helper
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Customer\Api\CustomerRepositoryInterface          $customerRepository
     * @param \Magento\Catalog\Helper\Image                              $imageHelper
     * @param \Magento\Store\Model\StoreManagerInterface                 $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface                $localeResolver
     */
    public function __construct(
        \Hyperpay\Extension\Helper\Data                            $helper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface          $customerRepository,
        \Magento\Catalog\Helper\Image                              $imageHelper,
        \Magento\Store\Model\StoreManagerInterface                 $storeManager,
        \Magento\Framework\Locale\ResolverInterface                $localeResolver
    )
    {
        $this->_helper = $helper;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->_customerRepository = $customerRepository;
        $this->_imageHelper = $imageHelper;
        $this->_storeManager = $storeManager;
        $this->_localeResolver = $localeResolver;
    }

    /**
     * Build the Tabby parameters of the payment request
     *
     * @param  Order $order
     * @param  bool  $testMode
     * @return array
     */
    public function build(Order $order, bool $testMode): array
    {
        $payment = $order->getPayment();
        $billing = $order->getBillingAddress();
        $shipping = $order->getShippingAddress() ?: $billing;

        $data = [
            'customer.email' => $order->getCustomerEmail(),
            'customer.givenName' => $billing->getFirstname(),
            'customer.surname' => $billing->getLastname(),
            'customer.mobile' => $billing->getTelephone(),
            'shipping.cost' => $this->formatAmount($this->_helper->convertPrice($payment, $order->getBaseShippingAmount())),
            'taxAmount' => $this->formatAmount($this->_helper->convertPrice($payment, $order->getBaseTaxAmount())),
            'locale' => substr((string)$this->_localeResolver->getLocale(), 0, 2),
            'customParameters[description]' => 'Order #' . $order->getIncrementId(),
            'customParameters[discount_amount]' => $this->formatAmount(
                $this->_helper->convertPrice($payment, abs((float)$order->getBaseDiscountAmount()))
            ),
            'customParameters[bill_number]' => $order->getIncrementId(),
            'customParameters[plugin]' => 'magento',
        ];

        if ($testMode) {
            $data['customer.email'] = self::TEST_EMAIL;
            $data['customer.mobile'] = self::TEST_MOBILE;
        }

        $data = array_merge(
            $data,
            $this->getAddress($billing, 'billing'),
            $this->getAddress($shipping, 'shipping'),
            $this->getCartItems($order),
            $this->getBuyerHistory($order)
        );

        $data['customParameters[attachment]'] = json_encode([
            'body' => ['payment_history_simple' => $this->getPaymentHistorySimple($order)],
            'content_type' => 'application/vnd.tabby.v1+json',
        ], JSON_UNESCAPED_SLASHES);

        return $data;
    }

    /**
     * Retrieve address parameters for billing or shipping
     *
     * @param  \Magento\Sales\Model\Order\Address $address
     * @param  string $type
     * @return array
     */
    protected function getAddress($address, $type)
    {
        $street = (array)$address->getStreet();

        return [
            $type . '.street1' => (string)reset($street),
            $type . '.city' => $address->getCity(),
            $type . '.state' => (string)$address->getRegion(),
            $type . '.country' => $address->getCountryId(),
            $type . '.postcode' => (string)$address->getPostcode(),
        ];
    }

    /**
     * Retrieve cart.items[i].* parameters of the visible order items
     *
     * @param  Order $order
     * @return array
     */
    protected function getCartItems(Order $order)
    {
        $payment = $order->getPayment();
        $data = [];
        $index = 0;

        foreach ($order->getAllVisibleItems() as $item) {
            $rowTotal = (float)$item->getBaseRowTotal();
            $discount = $rowTotal > 0 ? (float)$item->getBaseDiscountAmount() / $rowTotal * 100 : 0;
            $product = $item->getProduct();
            $prefix = "cart.items[$index].";

            $data[$prefix . 'name'] = $item->getName();
            $data[$prefix . 'price'] = $this->formatAmount($this->_helper->convertPrice($payment, $item->getBasePrice()));
            $data[$prefix . 'quantity'] = max(1, (int)round((float)$item->getQtyOrdered()));
            $data[$prefix . 'discount'] = $this->formatAmount(min(max($discount, 0), 100));
            $data[$prefix . 'sku'] = (string)($item->getSku() ?: $item->getProductId());

            if ($product) {
                $description = strip_tags((string)($product->getShortDescription() ?: $product->getDescription()));
                $data[$prefix . 'description'] = mb_substr(trim($description), 0, self::DESCRIPTION_LENGTH);
                $data[$prefix . 'productUrl'] = (string)$product->getProductUrl();
                $data[$prefix . 'imageUrl'] = $this->getImageUrl($product);
                $data[$prefix . 'type'] = $this->getProductType($product, $item);
            }

            $index++;
        }

        return $data;
    }

    /**
     * Retrieve the product base image url
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getImageUrl($product)
    {
        try {
            return (string)$this->_imageHelper->init($product, 'product_base_image')->getUrl();
        } catch (\Exception $e) {
            if (!$product->getImage()) {
                return '';
            }
            $mediaUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

            return $mediaUrl . 'catalog/product' . $product->getImage();
        }
    }

    /**
     * Retrieve the first category name, Tabby uses it as the item type
     *
     * @param  \Magento\Catalog\Model\Product $product
     * @param  \Magento\Sales\Model\Order\Item $item
     * @return string
     */
    protected function getProductType($product, $item)
    {
        $category = $product->getCategoryCollection()
            ->addAttributeToSelect('name')
            ->setPageSize(1)
            ->getFirstItem();

        if ($category && $category->getName()) {
            return (string)$category->getName();
        }

        return $item->getIsVirtual() ? 'digital' : 'physical';
    }

    /**
     * Tabby risk-scoring parameters built from the customer account and previous orders
     *
     * @param  Order $order
     * @return array
     */
    protected function getBuyerHistory(Order $order)
    {
        $orderHistory = [];
        foreach ($this->getPreviousOrders($order)->setOrder('created_at', 'DESC')->setPageSize(self::HISTORY_LIMIT) as $previous) {
            $method = $previous->getPayment() ? $previous->getPayment()->getMethod() : '';
            $orderHistory[] = [
                'purchased_at' => $this->formatDate($previous->getCreatedAt(), self::DATE_TIME_FORMAT),
                'amount' => $this->formatAmount($previous->getGrandTotal()),
                'status' => $this->mapOrderState($previous->getState()),
                'payment_method' => $method === 'cashondelivery' ? 'cod' : 'card',
            ];
        }

        $completedCount = count(array_filter($orderHistory, function ($history) {
            return $history['status'] === 'complete';
        }));

        $data = [
            'customParameters[buyer_history_registered_since]' => $this->formatDate(
                $this->getRegisteredSince($order),
                self::DATE_TIME_FORMAT
            ),
            'customParameters[buyer_history_loyalty_level]' => (string)$completedCount,
            'customParameters[buyer_history_wishlist_count]' => '0',
            'customParameters[buyer_history_is_social_networks_connected]' => '0',
            'customParameters[buyer_history_is_phone_number_verified]' => '0',
            'customParameters[buyer_history_is_email_verified]' => '0',
        ];

        // the connector rejects an empty order_history, so first-time buyers omit it
        if ($orderHistory) {
            $data['customParameters[order_history]'] = json_encode($orderHistory, JSON_UNESCAPED_SLASHES);
        }

        return $data;
    }

    /**
     * First and last paid purchase of the customer, excluding the current order
     *
     * @param  Order $order
     * @return array
     */
    protected function getPaymentHistorySimple(Order $order)
    {
        $first = $this->getPaidOrder($order, 'ASC');
        $last = $this->getPaidOrder($order, 'DESC');

        $history = [
            'unique_account_identifier' => $order->getCustomerId()
                ? (string)$order->getCustomerId()
                : (string)$order->getCustomerEmail(),
            'paid_before_flag' => (bool)$first,
        ];

        if ($first && $last) {
            $history['date_of_first_paid_purchase'] = $this->formatDate($first->getCreatedAt(), 'Y-m-d');
            $history['date_of_last_paid_purchase'] = $this->formatDate($last->getCreatedAt(), 'Y-m-d');
        }

        return $history;
    }

    /**
     * Retrieve the oldest or newest paid previous order
     *
     * @param  Order  $order
     * @param  string $direction
     * @return Order|null
     */
    protected function getPaidOrder(Order $order, $direction)
    {
        $paid = $this->getPreviousOrders($order)
            ->addFieldToFilter('state', ['in' => $this->_paidStates])
            ->setOrder('created_at', $direction)
            ->setPageSize(1)
            ->getFirstItem();

        return $paid && $paid->getId() ? $paid : null;
    }

    /**
     * Collection of the customer's orders placed before, excluding the current order
     *
     * @param  Order $order
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    protected function getPreviousOrders(Order $order)
    {
        return $this->_orderCollectionFactory->create()
            ->addFieldToFilter('customer_email', $order->getCustomerEmail())
            ->addFieldToFilter('entity_id', ['neq' => $order->getId()]);
    }

    /**
     * Retrieve the customer registration date, guests use the order date
     *
     * @param  Order $order
     * @return string
     */
    protected function getRegisteredSince(Order $order)
    {
        if ($order->getCustomerId()) {
            try {
                $createdAt = $this->_customerRepository->getById($order->getCustomerId())->getCreatedAt();
                if ($createdAt) {
                    return $createdAt;
                }
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                return $order->getCreatedAt();
            }
        }

        return $order->getCreatedAt();
    }

    /**
     * Map Magento order state to Tabby order history status
     *
     * @param  string $state
     * @return string
     */
    protected function mapOrderState($state)
    {
        switch ($state) {
            case Order::STATE_COMPLETE:
                return 'complete';
            case Order::STATE_PROCESSING:
                return 'processing';
            case Order::STATE_NEW:
            case Order::STATE_PENDING_PAYMENT:
            case Order::STATE_HOLDED:
            case Order::STATE_PAYMENT_REVIEW:
                return 'new';
            case Order::STATE_CLOSED:
                return 'refunded';
            case Order::STATE_CANCELED:
                return 'canceled';
            default:
                return 'unknown';
        }
    }

    /**
     * Format a UTC database date
     *
     * @param  string $date
     * @param  string $format
     * @return string
     */
    protected function formatDate($date, $format)
    {
        $timestamp = $date ? strtotime($date . ' UTC') : false;

        return gmdate($format, $timestamp ?: time());
    }

    /**
     * Format an amount with 2 decimals
     *
     * @param  float|string|null $amount
     * @return string
     */
    protected function formatAmount($amount)
    {
        return number_format((float)$amount, 2, '.', '');
    }
}
