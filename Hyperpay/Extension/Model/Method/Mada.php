<?php

namespace Hyperpay\Extension\Model\Method;


/**
 * Pay In Store payment method model
 */
class Mada extends \Hyperpay\Extension\Model\Method\MethodAbstract
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'HyperPay_Mada';
    public function getTitle()
    {
        return __("mada");
    }



}