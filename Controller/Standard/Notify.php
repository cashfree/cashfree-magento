<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;

/**
 * Class Notify
 * To notify customer when if there is any netword falure during payment
 * @package Cashfree\Cfcheckout\Controller\Standard\Notify
 */
class Notify extends \Cashfree\Cfcheckout\Controller\CfAbstract implements CsrfAwareActionInterface
{
    /**
     * @var \Psr\Log\LoggerInterface 
     */
    protected $logger;

    /**
     * @var \Cashfree\Cfcheckout\Model\Config
     */
    protected $config;

    /**
     * @var \Magento\Framework\App\Action\Context
     */

    protected $context;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Customer\Model\Session
    */
    protected $customerSession;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Cashfree\Cfcheckout\Model\Config $config
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     */

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) 
    {
        parent::__construct(
            $logger,
            $config,
            $context,
            $transaction,
            $customerSession,
            $checkoutSession,
            $invoiceService,
            $quoteRepository,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );

    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    
    /**
     * Execute webhook in case of network failure
     *
     * @return void
     */
    public function execute() {
        $request = $this->getRequest()->getParams();
        
        $order_id = strip_tags($request["orderId"]);
        $order = $this->objectManagement->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);
        $validateOrder = $this->validateWebhook($request, $order);

        $transactionId  = $request['referenceId'];

        $mageOrderStatus = $order->getStatus();

        if($mageOrderStatus === 'pending') {

            if(!empty($validateOrder['status']) && $validateOrder['status'] === true) {
                if($request['txStatus'] == 'SUCCESS') {
                    $request['additional_data']['cf_transaction_id'] = $transactionId;
                    $this->logger->info("Cashfree Notify processing started for cashfree transaction_id(:$transactionId)");
                    $this->processPayment($transactionId, $order);
                    $this->logger->info("Cashfree Notify processing complete for cashfree transaction_id(:$transactionId)");
                    return;
                } elseif($request['txStatus'] == 'FAILED' || $request['txStatus'] == 'CANCELLED') {
                    $orderStatus = self::STATE_CANCELED;
                    $this->processWebhookStatus($orderStatus, $order);
                    $this->logger->info("Cashfree Notify change magento order status to (:$orderStatus) cashfree transaction_id(:$transactionId)");
                    return;
                } elseif($request['txStatus'] == 'USER_DROPPED') {
                    $orderStatus = self::STATE_CLOSED;
                    $this->processWebhookStatus($orderStatus, $order);
                    $this->logger->info("Cashfree Notify change magento order status to (:$orderStatus) cashfree transaction_id(:$transactionId)");
                    return;
                } else {
                    $orderStatus = self::STATE_PENDING_PAYMENT;
                    $this->processWebhookStatus($orderStatus, $order);
                    $this->logger->info("Cashfree Notify change magento order status to (:$orderStatus) cashfree transaction_id(:$transactionId)");
                    return;
                }
            } else {
                $errorMsg = $validateOrder['errorMsg'];
                $this->logger->info("Cashfree Notify processing payment for cashfree transaction_id(:$transactionId) is failed due to ERROR(: $errorMsg)");
                return;
            }
        } else {
            $this->logger->info("Order has been already in processing state for cashfree transaction_id(:$transactionId)");
            return;
        }
    }

}
