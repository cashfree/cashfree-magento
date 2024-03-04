<?php

namespace Cashfree\Cfcheckout\Controller;

use Cashfree\Cfcheckout\Model\Config;
use Cashfree\Cfcheckout\Model\PaymentMethod;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\MailException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\State\AuthorizeCommand;
use Magento\Sales\Model\Order\Payment\State\CaptureCommand;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Cashfree Abstract Controller
 */
abstract class CfAbstract extends Action
{
    const STATE_PROCESSING      = 'processing';
    const STATE_PENDING_PAYMENT = 'pending_payment';
    const STATE_CANCELED        = 'canceled';
    const STATE_CLOSED          = 'closed';
    const API_VERSION_20220901    = '2022-09-01';
    const API_VERSION_20210521    = '2021-05-21';

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
     * @var ObjectManager
     */
    public $objectManagement;

    /**
     * @var CaptureCommand
     */
    protected $captureCommand;

    /**
     * @var AuthorizeCommand
     */
    protected $authorizeCommand;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Context $context
     * @param Transaction $transaction
     * @param Session $checkoutSession
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
        Session $checkoutSession,
        InvoiceService $invoiceService,
        CartRepositoryInterface $quoteRepository,
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender
    ) {
        parent::__construct($context);
        $this->logger           = $logger;
        $this->config           = $config;
        $this->orderSender      = $orderSender;
        $this->transaction      = $transaction;
        $this->invoiceSender    = $invoiceSender;
        $this->invoiceService   = $invoiceService;
        $this->checkoutSession  = $checkoutSession;
        $this->quoteRepository  = $quoteRepository;
        $this->orderRepository  = $orderRepository;
        $this->objectManagement = ObjectManager::getInstance();
        $this->captureCommand = new CaptureCommand();
        $this->authorizeCommand = new AuthorizeCommand();
    }

    /**
     * Instantiate quote and checkout
     *
     * @return void
     * @throws LocalizedException
     */
    protected function initCheckout()
    {
        $quote = $this->getQuote();
        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->getResponse()->setStatusHeader(403, '1.1', 'Forbidden');
            throw new LocalizedException(__('We can\'t initialize checkout.'));
        }
    }

    /**
     * Return checkout quote object
     *
     * @return Quote
     */
    protected function getQuote()
    {
        if (!$this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }

    /**
     * @param $request
     * @param $order
     * @return array
     */
    protected function validateWebhook($request, $order)
    {
        $orderId        = $request["orderId"];
        $cfOrderAmount  = $request["orderAmount"];
        $paymentMode    = $request["paymentMode"];
        $referenceId    = $request["referenceId"];
        $txStatus       = $request["txStatus"];
        $txTime         = $request["txTime"];
        $txMsg          = $request["txMsg"];
        $signature      = $request["signature"];

        $orderAmount    = round($order->getGrandTotal(), 2);
        if ($orderAmount != $cfOrderAmount)
        {
            $error_message = "Cart order amount = {$orderAmount} doesn't match with amount paid = {$cfOrderAmount}";
            $validation_content['errorMsg'] = $error_message;
            $validation_content['status'] = false;
            $this->logger->info(__("Cashfree Error: ".$error_message));
            return $validation_content;
        }

        $secretKey = $this->config->getConfigData('secret_key');

        $data = $orderId.$cfOrderAmount.$referenceId.$txStatus.$paymentMode.$txMsg.$txTime;

        $hash_hmac = hash_hmac('sha256', $data, $secretKey, true) ;
        $computedSignature = base64_encode($hash_hmac);

        if ($computedSignature != $signature) {
            $error_message = "Signature mismatch.";
            $validation_content['errorMsg'] = $error_message;
            $validation_content['status'] = false;
            $this->logger->info(__("Cashfree Error: ".$error_message));
            return $validation_content;
        }

        $validation_content['errorMsg'] = "";
        $validation_content['status'] = true;
        return $validation_content;
    }

    /**
     * @param $cfOrderId
     * @param $order
     * @return array
     */
    protected function checkRedirectOrderStatus($cfOrderId, $order)
    {
        $getPaymentUrl = $this->getOrderUrl()."/".$cfOrderId."/payments";

        $orderAmount    = round($order->getGrandTotal(), 2);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL             => $getPaymentUrl,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "GET",
            CURLOPT_HTTPHEADER      => [
                "Accept:            application/json",
                "Content-Type:      application/json",
                "x-api-version:     ".self::API_VERSION_20210521,
                "x-client-id:       ".$this->config->getAppId(),
                "x-client-secret:   ".$this->config->getSecretKey()
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $cfOrder = json_decode($response);

        if (null !== $cfOrder && !empty($cfOrder[0]->payment_status))
        {
            $cfOrderStatus          = $cfOrder[0]->payment_status;
            $cfOrderAmount          = $cfOrder[0]->order_amount;
            $transaction_message    = $cfOrder[0]->payment_message;
            if($cfOrderStatus === 'SUCCESS') {
                if($orderAmount === $cfOrderAmount) {
                    $validation_content['txMsg'] = $transaction_message;
                    $validation_content['status'] = $cfOrderStatus;
                    $validation_content['transaction_id'] = $cfOrder[0]->cf_payment_id;
                    $this->logger->info(__("Cashfree Success: ".$transaction_message));
                } else {
                    $transaction_message = "Cart order amount = {$orderAmount} doesn't match with amount paid = {$cfOrderAmount}";
                    $validation_content['txMsg'] = $transaction_message;
                    $validation_content['status'] = 'FAILED';
                    $this->logger->info(__("Cashfree Error: ".$transaction_message));
                }
                return $validation_content;
            } else {
                $validation_content['txMsg'] = $transaction_message;
                $validation_content['status'] = $cfOrderStatus;
                $this->logger->info(__("Cashfree Error: ".$transaction_message));
                return $validation_content;
            }
        } else {
            $transaction_message = "Cashfree Error: Order does not found. Please contact to merchant for support.";
            $validation_content['errorMsg'] = $transaction_message;
            $validation_content['status'] = "PENDING";
            $this->logger->info(__("Cashfree Error: ".$transaction_message));
            return $validation_content;
        }
    }

    protected function getCfOrderResponse($cfOrderId)
    {
        $getOrderUrl = $this->getOrderUrl()."/".$cfOrderId;

        $response = $this->getCurlResponse($getOrderUrl);

        return json_decode($response);
    }

    protected  function getCurlResponse($curlUrl)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL             => $curlUrl,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_ENCODING        => "",
            CURLOPT_MAXREDIRS       => 10,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST   => "GET",
            CURLOPT_HTTPHEADER      => [
                "Accept:            application/json",
                "Content-Type:      application/json",
                "x-api-version:     ".self::API_VERSION_20220901,
                "x-client-id:       ".$this->config->getAppId(),
                "x-client-secret:   ".$this->config->getSecretKey()
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }

    protected function processPayment($transactionId, $order) {
        $orderAmount = round($order->getGrandTotal(), 2);
        $order->setState(self::STATE_PROCESSING)->setStatus(self::STATE_PROCESSING);
        $payment = $order->getPayment();

        $payment->setAmountPaid($orderAmount)
            ->setLastTransId($transactionId)
            ->setTransactionId($transactionId)
            ->setIsTransactionClosed(true)
            ->setShouldCloseParentTransaction(true);

        $payment->setParentTransactionId($payment->getTransactionId());

        if ($this->config->getPaymentAction() === PaymentMethod::ACTION_AUTHORIZE_CAPTURE)
        {
            $payment->addTransactionCommentsToOrder("$transactionId", $this->captureCommand->execute($payment, $order->getGrandTotal() , $order) , "");
        }
        else
        {
            $payment->addTransactionCommentsToOrder("$transactionId", $this->authorizeCommand->execute($payment, $order->getGrandTotal() , $order) , "");
        }

        $transaction = $payment->addTransaction(TransactionInterface::TYPE_AUTH, null, true, "");
        $transaction->setIsClosed(true);
        $transaction->save();
        $order->save();
        $this->orderRepository->save($order);

        //update/disable the quote
        $quote = $this->objectManagement->get('Magento\Quote\Model\Quote')->load($order->getQuoteId());
        $quote->setIsActive(false)->save();

        if($order->canInvoice() && $this->config->canSendInvoice())
        {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($transactionId);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transaction->addObject($invoice)
                                                    ->addObject($invoice->getOrder());
            $transactionSave->save();

            $this->invoiceSender->send($invoice);
            //send notification code
            $order->setState(self::STATE_PROCESSING)->setStatus(self::STATE_PROCESSING);
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
            ->setIsCustomerNotified(true)
            ->save();
        }

        try
        {
            $this->checkoutSession->setCashfreeMailSentOnSuccess(true);
            $this->orderSender->send($order, true);
            $this->checkoutSession->unsCashfreeMailSentOnSuccess();
            $this->logger->info("Try sending mail.");
        }
        catch (MailException $exception)
        {
            $this->logger->info("catch mail exception.");
            $this->logger->critical($exception);
        }
        catch (\Exception $e)
        {
            $this->logger->info("catch exception.");
            $this->logger->critical($e);
        }
    }

    protected function processWebhookStatus($orderStatus, $order) {

        $order->setState($orderStatus)->setStatus($orderStatus);
        $order->save();

        $this->orderRepository->save($order);

        //update/disable the quote
        $quote = $this->objectManagement->get('Magento\Quote\Model\Quote')->load($order->getQuoteId());
        $quote->setIsActive(false)->save();
    }

    /**
     * Return url according to environment
     * @return string
     */
    protected function getOrderUrl() {
        $env = $this->config->getCfEnvironment();
        if ($env === 'production') {
            return $this->config->getConfigData('prod_url');
        }
        return $this->config->getConfigData('test_url');
    }
}
