<?php

namespace Hyperpay\Extension\Model;



class Hook implements \Hyperpay\Extension\Api\HookInterface
{

    protected $logger;
    protected $_jsonResultFactory;
    protected $_orderFactory;
    protected $request;
    protected $_adapter;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Hyperpay\Extension\Model\Adapter $_adapter
     * @param \Magento\Framework\App\Request\Http $request
     */
    public function __construct(
       \Psr\Log\LoggerInterface $logger,
       \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
       \Magento\Sales\Model\OrderFactory $orderFactory,
       \Hyperpay\Extension\Model\Adapter $_adapter,
       \Magento\Framework\Webapi\Rest\Request $request
    )
    {
       $this->request = $request;
       $this->_orderFactory = $orderFactory;
       $this->logger = $logger;
       $this->_jsonResultFactory = $jsonFactory;
        $this->_adapter  =$_adapter;

    }
    /**
     * webhook function
     *
     * @api
     * @return
     */
    public function hook()
    {
        try{
            $http_body = $this->request->getContent();
            $this->logger->info($http_body);
            $notification_key_from_configration = $this->_adapter->getWebhookKey();
            $headers = getallheaders();
            $iv_from_http_header = $headers['X-Initialization-Vector'];
            $auth_tag_from_http_header = $headers['X-Authentication-Tag'];
            $http=json_decode($http_body);
            $body = $http->encryptedBody;
            $key = hex2bin($notification_key_from_configration);
            $iv = hex2bin($iv_from_http_header);
            $auth_tag = hex2bin($auth_tag_from_http_header);
            $cipher_text = hex2bin($body);
            $result =json_decode(openssl_decrypt($cipher_text, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $auth_tag));
            if (preg_match('/^(000\.200)/', $result->payload->result->code)) {
                return json_encode(["status"=>"ok"]);
            }
            $order = $this->_orderFactory->create()->loadByIncrementId($result->merchantTransactionId);
            if(!$order) {
                $result = $this->_jsonResultFactory->create();
                $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND);
                $result->setData(['error_message' => "ORDER NOT FOUND"]);
                return $result;
            }

        }catch (\Exception $exception)
        {
            $this->logger->error($exception->getMessage());
            $result = $this->_jsonResultFactory->create();
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
            $result->setData(['error_message' => $exception->getMessage()]);
            return $result;
        }

        try{
            if($order->getState() === 'processing') {
                return "ok";
            }
            $this->_adapter->setInfo($order, $result->payload->id);
            $status = $this->_adapter->orderStatus(json_decode($result->payload,true), $order);
            if ($status !== 'success')
            {
                $order->cancel();
                $order->save();
            }
            return "ok";
        }catch(\Exception $exception)
        {
            $this->logger->error($exception->getMessage());
            $result = $this->_jsonResultFactory->create();
            $result->setHttpResponseCode(\Magento\Framework\Webapi\Exception::HTTP_INTERNAL_ERROR);
            $result->setData(['error_message' => $exception->getMessage()]);
            return $result;
        }
    }
}
