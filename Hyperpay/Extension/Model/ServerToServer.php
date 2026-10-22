<?php

namespace Hyperpay\Extension\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * Server-to-server payments (Tabby, CliQ): create the payment, build the
 * redirect form and query the payment status
 */
class ServerToServer
{
    const ACCEPT_CODE_PATTERN = '/^(000\.200|800\.400\.5|100\.400\.500)/';
    const RESOURCE_PATH_PATTERN = '#^/v1/payments/[A-Za-z0-9.\-]+/?$#';
    const TIMEOUT = 60;
    /**
     *
     * @var string[]
     */
    protected $_brands = [
        'HyperPay_Tabby' => 'TABBY',
        'HyperPay_CliQ' => 'CLIQ'
    ];
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
     *
     * @var \Hyperpay\Extension\Model\TabbyRequestBuilder
     */
    protected $_tabbyRequestBuilder;
    /**
     *
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    protected $_localeResolver;

    /**
     * Constructor
     *
     * @param \Hyperpay\Extension\Model\Adapter             $adapter
     * @param \Hyperpay\Extension\Helper\Data               $helper
     * @param \Hyperpay\Extension\Model\TabbyRequestBuilder $tabbyRequestBuilder
     * @param \Magento\Framework\Locale\ResolverInterface   $localeResolver
     */
    public function __construct(
        \Hyperpay\Extension\Model\Adapter             $adapter,
        \Hyperpay\Extension\Helper\Data               $helper,
        \Hyperpay\Extension\Model\TabbyRequestBuilder $tabbyRequestBuilder,
        \Magento\Framework\Locale\ResolverInterface   $localeResolver
    )
    {
        $this->_adapter = $adapter;
        $this->_helper = $helper;
        $this->_tabbyRequestBuilder = $tabbyRequestBuilder;
        $this->_localeResolver = $localeResolver;
    }

    /**
     * Create the payment and retrieve the form that redirects the shopper to the gateway
     *
     * @param  Order  $order
     * @param  string $returnUrl
     * @return array ['url', 'method', 'params']
     * @throws LocalizedException
     */
    public function initiate(Order $order, string $returnUrl): array
    {
        $params = $this->buildParams($order, $returnUrl);

        $this->_adapter->setPaymentTypeAndCurrency($order, $params['paymentType'], $params['currency']);
        $order->getPayment()->setAdditionalInformation(Adapter::MERCHANT_TX_ID, $params['merchantTransactionId']);
        $order->save();

        $this->setAuthHeader();
        $response = $this->_helper->postForm($this->_adapter->getUrl() . 'payments', $params, self::TIMEOUT);

        $code = isset($response['result']['code']) ? $response['result']['code'] : '';
        if (!preg_match(self::ACCEPT_CODE_PATTERN, $code) || empty($response['redirect']['url'])) {
            $error = $this->parseError($response);
            $order->addStatusHistoryComment('Payment request rejected: ' . $code . ' ' . $error, false);
            $order->save();
            throw new LocalizedException(__($error));
        }

        if (isset($response['id'])) {
            $this->_adapter->setInfo($order, $response['id']);
        }

        return $this->redirectForm($response['redirect']);
    }

    /**
     * Build the flat parameter array posted to /v1/payments
     *
     * @param  Order  $order
     * @param  string $returnUrl
     * @return array
     */
    public function buildParams(Order $order, string $returnUrl): array
    {
        $payment = $order->getPayment();
        $method = $payment->getData('method');
        $testMode = $this->_adapter->getEnv();
        $amount = $this->_helper->convertPrice($payment, $order->getBaseGrandTotal());

        $params = [
            'entityId' => $this->_adapter->getEntity($method),
            'amount' => number_format((float)$amount, 2, '.', ''),
            'currency' => $this->_adapter->getSupportedCurrencyCode($method),
            'paymentType' => $this->_adapter->getPaymentType($method),
            'merchantTransactionId' => $order->getIncrementId() . 'I' . random_int(100000, 999999),
            'paymentBrand' => $this->_brands[$method],
            'shopperResultUrl' => $returnUrl,
        ];

        if ($testMode) {
            $params['testMode'] = 'EXTERNAL';
        }

        if ($method === 'HyperPay_Tabby') {
            $params = array_merge($params, $this->_tabbyRequestBuilder->build($order, $testMode));
        } elseif ($method === 'HyperPay_CliQ') {
            $params['customParameters[locale]'] = substr((string)$this->_localeResolver->getLocale(), 0, 2) === 'ar'
                ? 'ar'
                : 'en';
        }

        return $params;
    }

    /**
     * Build the auto-submitted form from the redirect block of the gateway response
     *
     * @param  array $redirect
     * @return array ['url', 'method', 'params']
     */
    public function redirectForm(array $redirect): array
    {
        $params = [];
        $query = (string)parse_url($redirect['url'], PHP_URL_QUERY);
        foreach (array_filter(explode('&', $query), 'strlen') as $pair) {
            $parts = explode('=', $pair, 2);
            $params[urldecode($parts[0])] = isset($parts[1]) ? urldecode($parts[1]) : '';
        }

        if (!empty($redirect['parameters']) && is_array($redirect['parameters'])) {
            foreach ($redirect['parameters'] as $parameter) {
                if (isset($parameter['name'])) {
                    $params[$parameter['name']] = isset($parameter['value']) ? (string)$parameter['value'] : '';
                }
            }
        }

        return [
            'url' => $redirect['url'],
            'method' => (isset($redirect['method']) && strtoupper($redirect['method']) === 'GET') ? 'GET' : 'POST',
            'params' => $params,
        ];
    }

    /**
     * Retrieve the payment status of a resource path returned to shopperResultUrl
     *
     * @param  string $method
     * @param  string $resourcePath
     * @return array
     * @throws LocalizedException
     */
    public function status(string $method, string $resourcePath): array
    {
        if (!preg_match(self::RESOURCE_PATH_PATTERN, $resourcePath)) {
            throw new LocalizedException(__('Checkout id is not found'));
        }

        $url = $this->_adapter->getHostUrl() . $resourcePath . '?entityId=' . urlencode((string)$this->_adapter->getEntity($method));
        $this->setAuthHeader();
        $response = $this->_helper->getJson($url, self::TIMEOUT);

        if (!isset($response['result']['code'])) {
            throw new LocalizedException(__('Failed to get response from the payment gateway'));
        }

        return $response;
    }

    /**
     * Retrieve a readable error from a gateway response
     *
     * @param  array $response
     * @return string
     */
    public function parseError(array $response): string
    {
        if (isset($response['resultDetails']['ExtendedDescription'])) {
            $extended = json_decode(urldecode((string)$response['resultDetails']['ExtendedDescription']), true);
            if (is_array($extended)) {
                $errors = [];
                if (!empty($extended['details']) && is_array($extended['details'])) {
                    foreach ($extended['details'] as $detail) {
                        if (isset($detail['error'])) {
                            $errors[] = isset($detail['field']) ? $detail['field'] . ': ' . $detail['error'] : $detail['error'];
                        }
                    }
                }
                if ($errors) {
                    return implode('; ', $errors);
                }
                if (!empty($extended['message'])) {
                    return (string)$extended['message'];
                }
            }
        }

        if (!empty($response['result']['description'])) {
            return (string)$response['result']['description'];
        }

        return (string)__('Failed to get response from the payment gateway');
    }

    /**
     * Set the bearer token header on the shared curl client
     */
    protected function setAuthHeader()
    {
        $this->_helper->setHeaders(['Authorization' => 'Bearer ' . $this->_adapter->getAccessToken()]);
    }
}
