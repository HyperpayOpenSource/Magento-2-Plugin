<?php
namespace Hyperpay\Extension\Observer;
use Magento\Framework\Event\ObserverInterface;
/**
 * Sales order place  observer
 */
class BeforeOrderPlaceObserver implements ObserverInterface
{
    /**
     * Update items stock status and low stock date.
     *
     * @param EventObserver $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
	$order = $observer->getOrder();
	$code = $order->getPayment()->getMethod();
	if (strpos($code,'HyperPay') !== false)
	{
        	$order->setCanSendNewEmailFlag(false);
	}
    }
}
