<?php
namespace Hyperpay\Extension\Block\Adminhtml\Order\View;


class Custom extends \Magento\Backend\Block\Template
{
    /**
     *
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;
    /**
     *
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $_messageManager;
    /**
     *
     * @var \Magento\Backend\Helper\Data
     */
    protected $_helperBackend;
    /**
     *
     * @var \Hyperpay\Extension\Model\Adapter 
     */
    protected $_adapter;
    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    protected $_responseFactory;
    /**
     * Constructor
     * 
     * @param \Magento\Backend\Block\Template\Context     $context
     * @param \Hyperpay\Extension\Model\Adapter              $adapter
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Backend\Helper\Data                $HelperBackend
     * @param \Magento\Framework\App\ResponseFactory      $responseFactory
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Hyperpay\Extension\Model\Adapter $adapter,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Backend\Helper\Data $helperBackend,
        \Magento\Framework\App\ResponseFactory $responseFactory
    ) 
    { 
        parent::__construct($context);
        $this->_orderRepository = $orderRepository;
        $this->_adapter =$adapter;
        $this->_messageManager=$messageManager;
        $this->_helperBackend = $helperBackend;
        $this->_responseFactory = $responseFactory;

    }
    /**
     * Retrieve checkout id 
     *
     * @return string
     */  
    public function getCheckoutId()
    {

        return $this->_adapter->getCheckoutId($this->_initPayment());
    }
    /**
     * Retrieve payment from order id in url 
     *
     * @return object
     */  
    protected function _initPayment()
    {
        $id = $this->getRequest()->getParam('order_id');
        try {
            $order = $this->_orderRepository->get($id);
        } catch (\Exception $e)
        {
            $this->_messageManager->addError($e->getMessage());
            $this->_logger->critical($e->getMessage());
            $this->_responseFactory->create()->setRedirect($this->_helperBackend->getHomePageUrl())->sendResponse();;
            return;
        }

              return $order->getPayment();
    }

}