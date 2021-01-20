<?php

namespace Hyperpay\Extension\Model\Method;

 use \Magento\Sales\Model\Order as OrderStatus;
/**
 * Pay In Store payment method model
 */
abstract class MethodAbstract extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Is method a gateaway
     *
     * @var boolean
     */
    protected $_isGateway = true;
    /**
     * Is can capture
     *
     * @var boolean
     */
    protected $_canCapture = true;
    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefund = true;
    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canRefundInvoicePartial = true;
    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canCapturePartial = true;
     /**
     * Is a initalize needed
     *
     * @var boolean
     */
    protected $_isInitializeNeeded = true;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter
     */
    protected $_adapter;
    /**
     *
     * @var \Hyperpay\Extension\Helper\Data
     */
    protected $_helper;
    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context                        $context
     * @param \Magento\Framework\Registry                             $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory       $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory            $customAttributeFactory
     * @param \Magento\Payment\Helper\Data                            $paymentData
     * @param \Hyperpay\Extension\Model\Adapter                          $adapter
     * @param \Hyperpay\Extension\Helper\Data                            $helper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface      $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger                    $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb           $resourceCollection
     * @param array                                                   $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Hyperpay\Extension\Helper\Data $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = array()
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->_helper=$helper;
        $this->_adapter=$adapter;
    }


    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $order = $payment->getOrder();
        if ($payment->getAdditionalInformation('payment_type') == 'PA') {
            $refId = $payment->getAdditionalInformation('checkoutId');
            $url = $this->_adapter->getUrl()."payments/".$refId;
            $amountVal = $this->_helper->convertPrice($payment, $amount);
            if($this->_adapter->getEnv()) {
                $grandTotal = (int) $amountVal;
            }else {
                $grandTotal=number_format($amountVal, 2, '.', '');
            }

            $currency = $payment->getAdditionalInformation('currency');
 	    $auth = array('Authorization'=>'Bearer '.$this->_adapter->getAccessToken());
            $this->_helper->setHeaders($auth);
            $data = $this->_adapter->buildCaptureOrRefundRequest($payment,$currency, $grandTotal,"CP");

            try
            {
                $result=$this->_helper->getCurlReqData($url, $data);
            }
            catch (\Exception $e)
            {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(),false);
            $order->save();
            throw $e;
            }
            $payment->setAdditionalInformation('CAPTURE', $result['result']['description']);

            if (preg_match('/^(000\.400\.0|000\.400\.100)/', $result['result']['code'])
                || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $result['result']['code'])) {
                $order->setState(OrderStatus::STATE_COMPLETE);
                $order->addStatusHistoryComment($result['result']['description'],OrderStatus::STATE_COMPLETE);
                $payment->setStatus(self::STATUS_APPROVED)
                    ->setTransactionId($payment->getAdditionalInformation('checkoutId'))
                    ->setIsTransactionClosed(1)->save();

                $order->save();
            } else {
                $order->addStatusHistoryComment($result['result']['description'], false);
                $order->save();
                throw new \Exception("An error occurred while processing");
            }
        }
        else {
            $payment->setStatus(self::STATUS_APPROVED)
                ->setTransactionId($payment->getAdditionalInformation('checkoutId'))
                ->setIsTransactionClosed(1)->save();
            $order->setState(OrderStatus::STATE_COMPLETE);
            $order->setStatus(OrderStatus::STATE_COMPLETE);
            $order->save();
        }

        return $this;
    }
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $refId = $payment->getAdditionalInformation('checkoutId');
        $url = $this->_adapter->getUrl()."payments/".$refId;
        $amountVal = $this->_helper->convertPrice($payment, $amount);
        $order = $payment->getOrder();
        if($this->_adapter->getEnv()) {
            $grandTotal = (int) $amountVal;
        }else {
            $grandTotal=number_format($amountVal, 2, '.', '');
        }
        $currency = $payment->getAdditionalInformation('currency');
	$auth = array('Authorization'=>'Bearer '.$this->_adapter->getAccessToken());
        $this->_helper->setHeaders($auth);
        $data = $this->_adapter->buildCaptureOrRefundRequest($payment,$currency, $grandTotal,"RF");
        try
        {
            $result=$this->_helper->getCurlReqData($url, $data);
        }
        catch (\Exception $e)
        {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(),false);
            $order->save();
            throw $e;
        }
            $payment->setAdditionalInformation('Refund', $result['result']['description']);

            if (preg_match('/^(000\.400\.0|000\.400\.100)/', $result['result']['code'])
                || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $result['result']['code'])) {
                $order->setState(OrderStatus::STATE_CLOSED);
                $order->addStatusHistoryComment($result['result']['description'],OrderStatus::STATE_CLOSED);


                $order->save();
            } else {
                $order->addStatusHistoryComment($result['result']['description'], false);
                $order->save();
                throw new \Exception("An error occurred while processing");
            }
        return $this;
    }
}
