<?php

namespace Hyperpay\Extension\Model;



class Hook implements \Hyperpay\Extension\Api\HookInterface
{

    protected $logger;
    protected $_orderFactory;
    protected $request;
    protected $_adapter;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Hyperpay\Extension\Model\Adapter $_adapter
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
        \Psr\Log\LoggerInterface               $logger,
        \Magento\Sales\Model\OrderFactory      $orderFactory,
        \Hyperpay\Extension\Model\Adapter      $_adapter,
        \Magento\Framework\Webapi\Rest\Request $request
    )
    {
        $this->request = $request;
        $this->_orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->_adapter = $_adapter;

    }

    /**
     * webhook function
     *
     * @return
     * @api
     */
    public function hook()
    {
        try {
            $http_body = $this->request->getContent();
            $this->logger->info($http_body);
            $notification_key_from_configration = $this->_adapter->getWebhookKey();
            $headers = getallheaders();
            $iv_from_http_header = $headers['X-Initialization-Vector'];
            $this->logger->info('iv: ' . $iv_from_http_header);
            $auth_tag_from_http_header = $headers['X-Authentication-Tag'];
            $this->logger->info('tag: ' . $auth_tag_from_http_header);
            $http = json_decode($http_body);
            $body = $http->encryptedBody;
            $key = hex2bin($notification_key_from_configration);
            $iv = hex2bin($iv_from_http_header);
            $auth_tag = hex2bin($auth_tag_from_http_header);

            $ver = (float)phpversion();
            if ($ver < 7.1) {
                $cipher_text = hex2bin($http_body . $auth_tag_from_http_header);
                $result = \Sodium\crypto_aead_aes256gcm_decrypt($cipher_text, NULL, $iv, $key);
            } else {
                $cipher_text = hex2bin($body);
                $result = json_decode(openssl_decrypt($cipher_text, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $auth_tag));

            }
            $this->logger->info(json_encode($result));

            if (preg_match('/^(000\.200)/', $result->payload->result->code) || $result->type == 'test') {
                return "ok";
            }

            $order = $this->_orderFactory->create()->loadByIncrementId($result->payload->merchantTransactionId);
            if (!$order->getState()) {
                throw new \Magento\Framework\Webapi\Exception(
                    __('Order Not Found'),
                    0,
                    \Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND);
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            throw new \Magento\Framework\Webapi\Exception(
                __($exception->getMessage()),
                0,
                \Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
        }
        try {
            if ($order->getState() === 'processing') {
                return "ok";
            }
            $this->_adapter->setInfo($order, $result->payload->id);
            $status = $this->_adapter->orderStatus(json_decode(json_encode($result->payload), true), $order);
            if ($status !== 'success') {
                $order->cancel();
                $order->save();
            }
            return "ok";
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            throw new \Magento\Framework\Webapi\Exception(
                __($exception->getMessage()),
                0,
                \Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
        }
    }
}
