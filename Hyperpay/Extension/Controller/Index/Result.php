<?php

namespace Hyperpay\Extension\Controller\Index;

use Hyperpay\Extension\Model\Adapter;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Shopper return url of server-to-server payments
 */
class Result extends \Magento\Framework\App\Action\Action implements
    HttpGetActionInterface,
    HttpPostActionInterface,
    CsrfAwareActionInterface
{
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
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;
    /**
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context    $context
     * @param \Hyperpay\Extension\Model\Adapter        $adapter
     * @param \Hyperpay\Extension\Model\ServerToServer $serverToServer
     * @param \Magento\Sales\Model\OrderFactory        $orderFactory
     * @param \Psr\Log\LoggerInterface                 $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context    $context,
        \Hyperpay\Extension\Model\Adapter        $adapter,
        \Hyperpay\Extension\Model\ServerToServer $serverToServer,
        \Magento\Sales\Model\OrderFactory        $orderFactory,
        \Psr\Log\LoggerInterface                 $logger
    )
    {
        parent::__construct($context);
        $this->_adapter = $adapter;
        $this->_serverToServer = $serverToServer;
        $this->_orderFactory = $orderFactory;
        $this->_logger = $logger;
    }

    public function execute()
    {
        $method = (string)$this->getRequest()->getParam('method');
        $resourcePath = (string)$this->getRequest()->getParam('resourcePath');

        try {
            if (!$this->_adapter->isServerToServer($method)) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Order is not found'));
            }
            $data = $this->_serverToServer->status($method, $resourcePath);
            $merchantTransactionId = isset($data['merchantTransactionId']) ? (string)$data['merchantTransactionId'] : '';
            $order = $this->_orderFactory->create()->loadByIncrementId($this->_adapter->resolveIncrementId($merchantTransactionId));
            $payment = $order->getId() ? $order->getPayment() : null;
            if (!$payment
                || $payment->getData('method') !== $method
                || $payment->getAdditionalInformation(Adapter::MERCHANT_TX_ID) !== $merchantTransactionId
            ) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Order is not found'));
            }
        } catch (\Exception $e) {
            $this->_logger->error('[HyperPay][s2s] result failed for ' . $resourcePath . ': ' . $e->getMessage());
            return $this->redirect('checkout/onepage/failure', $e->getMessage());
        }

        try {
            if ($order->getState() === 'processing') {
                return $this->redirect('checkout/onepage/success');
            }
            $this->_adapter->setInfo($order, $data['id']);

            if ($this->_adapter->isPending($data['result']['code'])) {
                $this->_adapter->setPending($order, 'Payment awaiting confirmation: ' . $data['result']['code'] . ' ' . $data['result']['description']);
                $this->messageManager->addNoticeMessage(__('Payment awaiting confirmation'));
                return $this->redirect('checkout/onepage/success');
            }

            $status = $this->_adapter->orderStatus($data, $order);
            if ($status !== 'success') {
                return $this->redirect('checkout/onepage/failure', $status);
            }

            return $this->redirect('checkout/onepage/success');
        } catch (\Exception $e) {
            $this->_logger->error('[HyperPay][s2s] result failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            $order->addStatusHistoryComment('Exception message: ' . $e->getMessage(), false);
            $order->save();
            return $this->redirect('checkout/onepage/failure', $e->getMessage());
        }
    }

    /**
     * The gateway posts back without a form key
     *
     * @param  RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * The payment status is verified against the gateway instead of a form key
     *
     * @param  RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Redirect with an optional error message
     *
     * @param  string      $path
     * @param  string|null $error
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirect($path, $error = null)
    {
        if ($error !== null) {
            $this->messageManager->addErrorMessage($error);
        }
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($path);

        return $resultRedirect;
    }
}
