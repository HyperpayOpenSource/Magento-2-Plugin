<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 *
 * @package     Hyperpay
 * @copyright   Copyright (c) 2014 HYPERPAY
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Response controller
 * 
 */
 
$ExternalLibPath=Mage::getModuleDir('', 'Hyperpay') . DS . 'core' . DS .'copyandpay.php';
require_once ($ExternalLibPath);

class Hyperpay_ResponseController extends Mage_Core_Controller_Front_Action
{

    /**
     * Construct
     */
    public function _construct()
    {
        parent::_construct();
    }

   protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }   
    
    public function handleCpResponseAction()
    {
        $session = $this->_getCheckout();

        $order = Mage::getSingleton('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }
        
        echo $this->_getPostResponseActionUrl($order);
    }
    public function handleSadadResponse()
    {
        $session = $this->_getCheckout();

        $order = Mage::getSingleton('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        echo $this->_getPostResponseSadadActionUrl($order);
    }
    private function _getPostResponseSadadActionUrl($order)
    {
        $merchantRefNum = $_GET['MerchantRefNum'];
        if(empty($merchantRefNum))
        {
            Mage::throwException('Merchant Reference Number does not found');
        }
        $server = Mage::getSingleton('customer/session')->getServerMode();
        $serviceUrl = getSadadStatusUrl($server);
        $test = false;
        if ($server == 'live')
        {
            $test = true;
        }
        $reqArray = array("transaction_no"=>$merchantRefNum, "merchant_id"=>getMerchantId($order));
        $data = json_encode($reqArray);
        $decodedData = sadadCurlRequest($serviceUrl,$data,$test);
        if ($decodedData=="0") {
            $order->addStatusHistoryComment('Request successfully processed');
            $order->save();
            $order->sendNewOrderEmail();
            Mage::helper('hyperpay')->invoice($order);
            Mage::getModel('sales/quote')->load($order->getQuoteId())->setIsActive(false)->save();
            $pageName = 'checkout/onepage/success/';
        }else
        {
            $returnMessage = $errorMessage = $_GET['ErrorDescription'];
            $order->cancel();
            $order->addStatusHistoryComment($errorMessage);
            $returnMessage .=" ( transaction id : " . $order->getIncrementId() . " )";
            $order->cancel()->save();
            $pageName = 'hyperpay/response/addErrorAndRedirect/';
            $params = array('message' => $returnMessage);
        }
        $params['_secure'] = true;
        $this->_redirect($pageName,$params);
    }
    private function _getPostResponseActionUrl(Mage_Sales_Model_Order $order)
    {

        $id = $_GET['id'];
        $server = Mage::getSingleton('customer/session')->getServerMode();
        $url = getStatusUrl($server, $id, $order);
        $resultJson = checkStatusPayment($server,$url,$order);
        $returnMessage =$resultJson['result']['description'];
	 if (Mage::getStoreConfig('cataloginventory/options/can_subtract', $order->getStoreId()) == true) {
            $invoiceItems = $order->getAllItems();
            foreach ($invoiceItems as $item) {
                $productId = $item->getProductId();
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
                $qty = $stockItem->getQty() - $item->getQtyOrdered();
                $stockItem->setQty($qty);
                $stockItem->setIsInStock((bool)$qty);
                $stockItem->save();

            }
        }
        $params = array();
        if (preg_match('/^(000\.400\.0|000\.400\.100)/', $resultJson['result']['code'])
            || preg_match('/^(000\.000\.|000\.100\.1|000\.[36])/', $resultJson['result']['code'])) {
            $order->getPayment()->setAdditionalInformation('IDENTIFICATION_REFERENCEID',$resultJson['id']);
            $order->getPayment()->setAdditionalInformation('CURRENCY',$resultJson['currency']);
            $order->getPayment()->setAdditionalInformation('hyperpay_transaction_code',$resultJson['paymentType']);
            $order->save();
            $order->sendNewOrderEmail();
            if ($resultJson['paymentType'] == 'PA') {
                $order->setState(Mage_Sales_Model_Order::STATE_NEW, true)->save();
            } else {
                Mage::helper('hyperpay')->invoice($order);
            }

            Mage::getModel('sales/quote')->load($order->getQuoteId())->setIsActive(false)->save();
            $pageName = 'checkout/onepage/success/';
        } else {
            $order->addStatusHistoryComment($resultJson['result']['description']);
            $returnMessage .=" ( transaction id : " . $order->getIncrementId() . " )";
            $order->cancel()->save();
            $pageName = 'hyperpay/response/addErrorAndRedirect/';
                $params = array('message' => $returnMessage);

        }
        $params['_secure'] = true;
        $this->_redirect($pageName,$params);
    }

    /**
     * Add error and redirect to the cart
     */
    public function addErrorAndRedirectAction()
    {
        $message = $this->myurldecode($this->getRequest()->getParam('message'));
        if (!empty($message)) {
            Mage::getSingleton('core/session')->addError($message);
        }

        $this->_redirect('checkout/cart/');
    }

    /**
     * Render the Payment Form page
     */
    public function renderCCAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('hyperpay/payment_formcc');

        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }
    
    public function renderDDAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('hyperpay/payment_formdd');

        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    public function redirectPayPalAction()
    {
        $this->loadLayout();
        $this->getLayout()->getBlock('root')->setTemplate('hyperpay/payment/formcp.phtml');
        $this->renderLayout();
    }
    
    public function renderCPAction()
    {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('hyperpay/payment_formcp');

        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }
    
    public function myurlencode($val)
    {
        return urlencode(str_replace("/", "%2f", $val));
    }

    public function myurldecode($val)
    {
        return str_replace("%2f", "/", urldecode($val));
    }

    public function __setState($order, $state, $status = false, $comment = '',
        $isCustomerNotified = null, $shouldProtectState = false)
    {
        // attempt to set the specified state
        if ($shouldProtectState) {
            if ($order->isStateProtected($state)) {
                Mage::throwException(
                    Mage::helper('sales')->__('The Order State "%s" must not be set manually.', $state)
                );
            }
        }
        $order->setData('state', $state);

        // add status history
        if ($status) {
            if ($status === true) {
                $status = $state;
            }
            $order->setStatus($status);
            $history = $order->addStatusHistoryComment($comment, false); // no sense to set $status again
            $history->setIsCustomerNotified($isCustomerNotified); // for backwards compatibility
        }
        return $this;
    }   
    
}
