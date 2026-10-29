<?php
namespace Hyperpay\Extension\Block;

class Redirect extends \Magento\Framework\View\Element\Template
{
    /**
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;
    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry                      $coreRegistry
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $coreRegistry
    )
    {
        parent::__construct($context);
        $this->_coreRegistry = $coreRegistry;
    }
    /**
     * Retrieve the gateway redirect form: url, method and params
     *
     * @return array
     */
    public function getRedirect()
    {
        return (array)$this->_coreRegistry->registry('hyperpay_redirect');
    }
}
