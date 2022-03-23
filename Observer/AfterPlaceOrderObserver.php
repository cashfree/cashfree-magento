<?php

namespace Cashfree\Cfcheckout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Payment;
use Cashfree\Cfcheckout\Model\PaymentMethod;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Class AfterPlaceOrderObserver
 * @package PayU\PaymentGateway\Observer
 */
class AfterPlaceOrderObserver implements ObserverInterface
{

    /**
     * Store key
     */
    const STORE = 'store';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AfterPlaceOrderRepayEmailProcessor
     */
    private $emailProcessor;

    /**
     * StatusAssignObserver constructor.
     *
     * @param OrderRepositoryInterface $orderRepository
     * @param AfterPlaceOrderRepayEmailProcessor $emailProcessor
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->orderRepository  = $orderRepository;
        $this->checkoutSession  = $checkoutSession;
        $this->config           = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Observer $observer)
    { 
        /** @var Payment $payment */
        $payment    = $observer->getData('payment');

        $pay_method = $payment->getMethodInstance();

        $code       = $pay_method->getCode();

        if($code === PaymentMethod::METHOD_CODE)
        {
            $this->assignStatus($payment);
            $this->checkoutSession->setCashfreeMailSentOnSuccess(false);
        }
        
    }

    /**
     * @param Payment $payment
     *
     * @return void
     */
    private function assignStatus(Payment $payment)
    {
        $order = $payment->getOrder();

        $new_order_status = $this->config->getNewOrderStatus();

        $order->setState('new')
              ->setStatus($new_order_status);

        $this->orderRepository->save($order);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $lastQuoteId = $order->getQuoteId();
        $quote = $objectManager->get('Magento\Quote\Model\Quote')->load($lastQuoteId);
        $quote->setIsActive(true)->save();
        $this->checkoutSession->replaceQuote($quote);
    }

}