<?php

namespace Hyperpay\Extension\Model;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Payment\Helper\Data as PaymentHelper;

class MainConfigProvider implements ConfigProviderInterface
{

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'HyperPay_Amex',
        'HyperPay_Mada',
        'HyperPay_Master',
        'HyperPay_PayPal',
        'HyperPay_SadadNcb',
        'HyperPay_SadadPayware',
        'HyperPay_Visa',
        'HyperPay_ApplePay',
        'HyperPay_stc'
    ];
    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;
    /**
     *
     * @var \Hyperpay\Extension\Helper\Data
     */
    protected $_helper;
    /**
     * @param CurrentCustomer $currentCustomer
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        CurrentCustomer $currentCustomer,
        PaymentHelper $paymentHelper,
        \Hyperpay\Extension\Helper\Data $helper
    ) {
        $this->currentCustomer = $currentCustomer;
        $this->paymentHelper = $paymentHelper;
        $this->_helper = $helper;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        }
    }
    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = ['payment' => []];
        foreach ($this->methodCodes as $code) {
                $config['payment'][$code]['paymentAcceptanceMarkSrc'] = $this->_helper->getPaymentMarkImageUrl($code);
        }

        return $config;
    }

}