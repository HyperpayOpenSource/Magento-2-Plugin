<?php
namespace Hyperpay\Extension\Block;



class Status extends \Magento\Framework\View\Element\Template
{
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
     * Constructor
     * 
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry                      $coreRegistry
     * @param \Hyperpay\Extension\Helper\Data                     $helper
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Hyperpay\Extension\Helper\Data $helper
    ) 
    {
        parent::__construct($context);
        $this->_helper=$helper;
        $this->_coreRegistry=$coreRegistry;

    }
    /**
     * Retrieve true if payment succeed
     *
     * @return bool
     */
    public function getStatus()
    {
        return $this->_coreRegistry->registry('status');
    }
    /**
     * Retrieve incremental order id
     *
     * @return string
     */  
    public function getOrderId()
    {
        return $this->_helper->getOrderId();
    }
}
