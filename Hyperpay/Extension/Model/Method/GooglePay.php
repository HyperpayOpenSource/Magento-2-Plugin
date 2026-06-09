<?php

namespace Hyperpay\Extension\Model\Method;

/**
 * HyperPay Google Pay payment method model
 */
class GooglePay extends \Hyperpay\Extension\Model\Method\MethodAbstract
{
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'HyperPay_GooglePay';
}
