<?php
namespace Hyperpay\Extension\Block;



class Display extends \Magento\Framework\View\Element\Template
{
    /**
     *
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $_store;
    /**
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;
    /**
     *
     * @var \Hyperpay\Extension\Helper\Data
     */
    protected $_helper;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter 
     */
    protected $_adapter;
    /**
     * Constructor
     * 
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Locale\Resolver               $store
     * @param \Magento\Framework\Registry                      $coreRegistry
     * @param \Hyperpay\Extension\Model\Adapter                   $adapter
     * @param \Hyperpay\Extension\Helper\Data                     $helper
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Locale\Resolver $store,
        \Magento\Framework\Registry $coreRegistry,
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Hyperpay\Extension\Helper\Data $helper
    ) 
    { 
        parent::__construct($context);
        $this->_helper=$helper;
        $this->_store = $store;
        $this->_adapter = $adapter;
        $this->_coreRegistry = $coreRegistry;
    }
    /**
     * Retrieve payment from brand 
     *
     * @return string
     */  
    public function getPaymentBrand()
    {   
        return $this->_helper->getBrand();
    }
    /**
     * Retrieve payment script  
     *
     * @return string
     */  
    public function getFormUrl()
    {    
        return $this->_coreRegistry->registry('formurl');
    }
    /**
     * Retrieve payment form shopper url
     *
     * @return string
     */  
    public function getShopperUrl()
    {
        return $this->_helper->getRedirectUrl();
    }
    /**
     * Retrieve local for paymrnt form 
     *
     * @return string
     */  
    public function getLang()
    {
        $loca= $this->_store->getLocale();
        $result = substr($loca, 0, 2);
        return $result;
    }
    /**
     * Retrieve payment form style
     *
     * @return string
     */  
    public function getStyle()
    {
        return $this->_adapter->getStyle();
    }
    /**
     * Retrieve payment form css
     *
     * @return string
     */  
    public function getCss()
    {
        return $this->_adapter->getCss();
    }
}
