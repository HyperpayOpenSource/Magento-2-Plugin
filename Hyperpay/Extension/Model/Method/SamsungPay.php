<?php

namespace Hyperpay\Extension\Model\Method;

/**
 * HyperPay Samsung Pay payment method model
 */
class SamsungPay extends \Hyperpay\Extension\Model\Method\MethodAbstract
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'HyperPay_SamsungPay';
}
