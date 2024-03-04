<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Cashfree\Cfcheckout\Controller\CfAbstract;
use Cashfree\Cfcheckout\Helper\Cfcheckout;
use Cashfree\Cfcheckout\Model\Config;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\DB\Transaction;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

class Response extends CfAbstract
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Context
     */

    protected $context;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var Session
    */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Context $context
     * @param Transaction $transaction
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param InvoiceService $invoiceService
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        LoggerInterface $logger,
        Config $config,
        Context $context,
        Transaction $transaction,
        OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        Cfcheckout $checkoutHelper,
        InvoiceService $invoiceService,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender
    ) {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $checkoutSession,
            $invoiceService,
            $quoteRepository,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );
        $this->orderFactory     = $orderFactory;
    }

    /**
     * Get order response from cashfree to complete order
     * @return Redirect
     * @throws Exception
     */
    public function execute()
    {
        $request = $this->getRequest()->getParams();
        $resultRedirect = $this->resultRedirectFactory->create();
        $orderIncrementId = $request['cf_id'];
        $order = $this->orderFactory->create()->loadByIncrementId($orderIncrementId);
        $validateOrder = $this->checkRedirectOrderStatus($orderIncrementId, $order);
        if ($validateOrder['status'] == "SUCCESS") {
            $mageOrderStatus = $order->getStatus();
            if($mageOrderStatus === 'pending') {
                $this->processPayment($validateOrder['transaction_id'], $order);
            }
            $this->messageManager->addSuccess(__('Your payment was successful'));
            $resultRedirect->setPath('checkout/onepage/success');
            return $resultRedirect;

        } else if ($validateOrder['status'] == "CANCELLED") {
            $this->messageManager->addWarning(__('Your payment was cancel'));
            $this->checkoutSession->restoreQuote();
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        } else if ($validateOrder['status'] == "FAILED") {
            $this->messageManager->addErrorMessage(__('Your payment was failed'));
            $order->cancel();
            $order->save();
            $order->setState(CfAbstract::STATE_CANCELED)->setStatus(CfAbstract::STATE_CANCELED);
            $order->save();
            $resultRedirect->setPath('checkout/onepage/failure');
            return $resultRedirect;
        } else if($validateOrder['status'] == "PENDING"){
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addWarning(__('Your payment is pending'));
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        } else{
            $this->checkoutSession->restoreQuote();
            $this->messageManager->addErrorMessage(__('There is an error. Payment status is pending'));
            $resultRedirect->setPath('checkout/cart');
            return $resultRedirect;
        }

    }
}
