define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'HyperPay_Master',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_ApplePay',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_Mada',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_Visa',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_PayPal',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_Amex',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_SadadNcb',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            },
            {
                type: 'HyperPay_SadadPayware',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/SadadPayware'
            },
	    {
                type: 'HyperPay_stc',
                component: 'Hyperpay_Extension/js/view/payment/method-renderer/DefaultPaymentMethods'
            }
        );
        return Component.extend({});
    }
);
