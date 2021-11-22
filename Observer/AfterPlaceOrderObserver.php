<?php

namespace Cashfree\Cfcheckout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment;
use Cashfree\Cfcheckout\Model\PaymentMethod;
use Magento\Framework\Event\ObserverInterface;

/**
 * Class AfterPlaceOrderObserver
 * @package Cashfree\Cfcheckout\Observer
 */
class AfterPlaceOrderObserver implements ObserverInterface
{
    /**
     * Store key
     */
    const STORE = 'store';

    /**
     * StatusAssignObserver constructor.
     */
    public function __construct(
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Cashfree\Cfcheckout\Model\PaymentMethod $paymentMethod,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction
    ) {
        $this->config           = $config;
        $this->checkoutSession  = $checkoutSession;
        $this->paymentMethod  = $paymentMethod;
        $this->invoiceService   = $invoiceService;
        $this->transaction      = $transaction;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Observer $observer)
    {

        $order = $observer->getOrder();

        /** @var Payment $payment */
        $payment = $order->getPayment();

        $pay_method = $payment->getMethodInstance();

        $code = $pay_method->getCode();

        if($code === PaymentMethod::METHOD_CODE)
        {
            $this->updateOrderLinkStatus($payment);
            
        }
    
    }

    /**
     * @param Payment $payment
     *
     * @return void
     */
    private function updateOrderLinkStatus(Payment $payment)
    {
        $order = $payment->getOrder();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $lastQuoteId = $order->getQuoteId();
        
        $cfTransactionId  = $payment->getLastTransId();

        if(empty($cfTransactionId) === false)
        {
            //get cashfree orderLink
            $orderLinkCollection = $objectManager->get('Cashfree\Cfcheckout\Model\OrderLink')
                                                       ->getCollection()
                                                       ->addFieldToSelect('entity_id')
                                                       ->addFieldToSelect('order_placed')
                                                       ->addFilter('quote_id', $lastQuoteId)
                                                       ->addFilter('cf_reference_id', $cfTransactionId)
                                                       ->addFilter('increment_order_id', $order->getRealOrderId())
                                                       ->getFirstItem();

            $orderLink = $orderLinkCollection->getData();

            if (empty($orderLink['entity_id']) === false and !$orderLink['order_placed'])
            {

                $order->addStatusHistoryComment(
                            __('Order has been successfuly paid by cashfree.')
                        );
                $order->save();

                //update quote
                $quote = $objectManager->get('Magento\Quote\Model\Quote')->load($lastQuoteId);
                $quote->setIsActive(false)->save();
                $this->checkoutSession->replaceQuote($quote);

                //update cashfree orderLink
                $orderLinkCollection->setOrderId($order->getEntityId())
                                    ->setOrderPlaced(true)
                                    ->save();
            }
        }
    }

    public function generateInvoice($order)
    {
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->save();
        $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
            $order->setIsCustomerNotified(true)
            ->save();
    }
}
