<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

/**
 * Class Notify
 * To notify customer when if there is any netword falure during payment
 * @package Cashfree\Cfcheckout\Controller\Standard\Notify
 */
class Notify extends \Cashfree\Cfcheckout\Controller\CfAbstract
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagement;

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
     * @param \\Magento\Framework\DB\Transaction $transaction
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Store\Model\StoreManagerInterface $storeManagement
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
        \Magento\Store\Model\StoreManagerInterface $storeManagement,
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
            $storeManagement,
            $orderRepository,
            $orderSender,
            $invoiceSender
        );

    }
    
    /**
     * Execute webhook in case of network failure
     *
     * @return void
     */
    public function execute() {
        $params = $this->getRequest()->getParams();
        $order_id = strip_tags($params["orderId"]);
        $order = $this->objectManagement->create('Magento\Sales\Model\Order')->loadByIncrementId($order_id);
        $order_status = $order->getStatus();
        
        if($order_status != self::ACTION_PROCESSING) {
            if($params['txStatus'] == 'SUCCESS') {
                $orderAmount    = $params['orderAmount'];
                $transactionId  = $params['referenceId'];

                $request['additional_data'] = array(
                    'cf_transaction_id'     => $transactionId,
                    'cf_order_id'           => $params['orderId'],
                    'cf_transaction_amount' => $orderAmount,
                    'cf_order_status'       => 'PAID'
                );

                $this->logger->info("Cashfree Notify processing started for cashfree transaction_id(:$transactionId)");

                $this->validateSignature($request, $order);

                $validateOrder = $this->validateSignature($request, $order);

                if(!empty($validateOrder['status']) && $validateOrder['status'] === true) {
                    $this->processPayment($request, $order);
                    $this->logger->info("Cashfree Notify processing complete for cashfree transaction_id(:$transactionId)");
                    return;
                } else {
                    $errorMsg = $validateOrder['errorMsg'];
                    $this->logger->info("Cashfree Notify processing payment for cashfree transaction_id(:$transactionId) is failed due to ERROR(: $errorMsg)");
                    return;
                }
                
            }
        }
        return;
    }

}
