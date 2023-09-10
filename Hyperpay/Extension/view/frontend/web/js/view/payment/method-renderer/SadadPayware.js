define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/action/place-order'
    ],
    function ($,Component,url,placeOrderAction) {
        'use strict';
 
        return Component.extend(
            {
                defaults: {
                    template: 'Hyperpay_Extension/payment/hyperpay',
                    redirectAfterPlaceOrder: false
                },
                afterPlaceOrder: function () {
                    window.location.replace(url.build('hyperpay/index/sadad'));
                },
                getPaymentAcceptanceMarkSrc: function () {
                    return window.checkoutConfig.payment[this.getCode()].paymentAcceptanceMarkSrc;
                }

            }
        );
    }
);
