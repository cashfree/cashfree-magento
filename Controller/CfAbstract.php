<?php

namespace Cashfree\Cfcheckout\Controller;

use \Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;

/**
 * Cashfree Abstract Controller
 */
abstract class CfAbstract extends \Magento\Framework\App\Action\Action
{
    const ACTION_PROCESSING = 'processing';

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
     * @param \Magento\Framework\DB\Transaction $transaction
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
    ) {
        parent::__construct($context);
        $this->logger           = $logger;
        $this->config           = $config;
        $this->orderSender      = $orderSender;
        $this->transaction      = $transaction;
        $this->invoiceSender    = $invoiceSender;
        $this->invoiceService   = $invoiceService;
        $this->customerSession  = $customerSession;
        $this->checkoutSession  = $checkoutSession;
        $this->quoteRepository  = $quoteRepository;
        $this->orderRepository  = $orderRepository;
        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
    }

    /**
     * Instantiate quote and checkout
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function initCheckout()
    {
        $quote = $this->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new \Magento\Framework\Exception\LocalizedException(__('We can\'t initialize checkout.'));
        }
    }

    /**
     * Return checkout quote object
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        if (!$this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }

    //Validate order is paid or not from cashfree order api
    protected function validateSignature($request, $order)
    {
        $cfOrderId      = $request['additional_data']['cf_order_id'];
        $cfOrderStatus  = $request['additional_data']['cf_order_status'];
        $cfOrderAmount  = $request['additional_data']['cf_transaction_amount'];
        $orderAmount    = round($order->getGrandTotal(), 2);

        if ($orderAmount != $cfOrderAmount)
        {
            $error_message = "Cart order amount = {$orderAmount} doesn't match with amount paid = {$cfOrderAmount}";
            $validation_content['errorMsg'] = $error_message;
            $validation_content['status'] = false;
            $this->logger->info(__("Cashfree Error: ".$error_message));
            return $validation_content;
        }

        $getOrderUrl = $this->getOrderUrl()."/".$cfOrderId;

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL             => $getOrderUrl,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "GET",
            CURLOPT_HTTPHEADER      => [
                "Accept:            application/json",
                "Content-Type:      application/json",
                "x-api-version:     2021-05-21",
                "x-client-id:       ".$this->config->getConfigData('app_id'),
                "x-client-secret:   ".$this->config->getConfigData('secret_key')
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        $order = json_decode($response);

        if (null !== $order && !empty($order->order_status))
        {
            if($cfOrderStatus !== $order->order_status && $order->order_status !== 'PAID') {
                $error_message = "Signature mismatch.";
                $validation_content['errorMsg'] = $error_message;
                $validation_content['status'] = false;
                $this->logger->info(__("Cashfree Error: ".$error_message));
                return $validation_content;
            }
        } else {
            $error_message = "Cashfree Error: Order does not found. Please contact to merchant for support.";
            $validation_content['errorMsg'] = $error_message;
            $validation_content['status'] = false;
            $this->logger->info(__("Cashfree Error: ".$error_message));
            return $validation_content;
        }

        $validation_content['errorMsg'] = "";
        $validation_content['status'] = true;
        return $validation_content;
    }

    protected function processPayment($request, $order) {
        $transactionId = $request['additional_data']['cf_transaction_id'];
        
        $orderAmount = round($order->getGrandTotal(), 2);
        
        $order->setState(self::ACTION_PROCESSING)->setStatus(self::ACTION_PROCESSING);

        $payment = $order->getPayment();

        $payment->setAmountPaid($orderAmount)
            ->setLastTransId($transactionId)
            ->setTransactionId($transactionId)
            ->setIsTransactionClosed(true)
            ->setShouldCloseParentTransaction(true);

        $payment->setParentTransactionId($payment->getTransactionId());

        $payment->addTransactionCommentsToOrder(
            "$transactionId",
            (new CaptureCommand())->execute(
                $payment,
                $order->getGrandTotal(),
                $order
            ),
            ""
        );

        $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH, null, true, "");
        $transaction->setIsClosed(true);
        $transaction->save();

        $order->save();

        $this->orderRepository->save($order);

        //update/disable the quote
        $quote = $this->objectManagement->get('Magento\Quote\Model\Quote')->load($order->getQuoteId());
        $quote->setIsActive(false)->save();

        if($order->canInvoice())
        {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($transactionId);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transaction->addObject($invoice)
                                                    ->addObject($invoice->getOrder());
            $transactionSave->save();

            $this->invoiceSender->send($invoice);
            //send notification code
            $order->setState(self::ACTION_PROCESSING)->setStatus(self::ACTION_PROCESSING);
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
            ->setIsCustomerNotified(true)
            ->save();
        }

        try
        {
            $this->checkoutSession->setCashfreeMailSentOnSuccess(true);
            $this->orderSender->send($order);
            $this->checkoutSession->unsCashfreeMailSentOnSuccess();
        }
        catch (\Magento\Framework\Exception\MailException $exception)
        {
            $this->logger->critical($e);
        }
        catch (\Exception $e)
        {
            $this->logger->critical($e);
        }
    }

    /**
     * Return url according to environment
     * @return string
     */
    protected function getOrderUrl() {
        $env = $this->config->getConfigData('environment');
        if ($env === 'production') {
            return $this->config->getConfigData('prod_url');
        }
        return $this->config->getConfigData('test_url');
    }
}