<?php

namespace Cashfree\Cfcheckout\Controller\Standard;

use Magento\Framework\Controller\ResultFactory;

class Response extends \Cashfree\Cfcheckout\Controller\CfAbstract
{
    protected $quote;
    
    protected $cache;
    
    protected $logger;
    
    protected $cartManagement;
    
    protected $checkoutSession;
    
    protected $orderRepository;
    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Cashfree\Cfcheckout\Model\Config $config
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Cashfree\Cfcheckout\Model\Config $config,
        \Magento\Framework\App\CacheInterface $cache,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Cashfree\Cfcheckout\Model\CheckoutFactory $checkoutFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $checkoutSession,
            $config
        );

        $this->config           = $config;
        $this->cartManagement   = $cartManagement;
        $this->customerSession  = $customerSession;
        $this->checkoutFactory  = $checkoutFactory;
        $this->cache            = $cache;
        $this->orderRepository  = $orderRepository;
        $this->logger           = $logger;
        $this->objectManagement = \Magento\Framework\App\ObjectManager::getInstance();
    }

    /**
     * Get order response from cashfree to complete order
     * @return array
     */
    public function execute()
    {
        $quote_id = $this->getQuote()->getId();
        
        if (empty($this->cache->load("quote_processing_".$quote_id)) === false)
        {
            $responseContent = [
                'success'   => true,
                'order_id'  => false,
                'parameters' => []
            ];

            # fetch the related sales order and verify the payment ID with cashfree reference id
            # To avoid duplicate order entry for same quote
            $collection = $this->_objectManager->get('Magento\Sales\Model\Order')
                                                ->getCollection()
                                                ->addFieldToSelect('entity_id')
                                                ->addFilter('quote_id', $quote_id)
                                                ->getFirstItem();

            $salesOrder = $collection->getData();

            if (empty($salesOrder['entity_id']) === false)
            {
                $this->logger->info("Cashfree inside order already processed with notify quoteID:" . $quote_id
                                ." and OrderID:".$salesOrder['entity_id']);

                $this->checkoutSession
                        ->setLastQuoteId($this->getQuote()->getId())
                        ->setLastSuccessQuoteId($this->getQuote()->getId())
                        ->clearHelperData();

                $order = $this->orderRepository->get($salesOrder['entity_id']);

                if ($order) {
                    $this->checkoutSession->setLastOrderId($order->getId())
                                        ->setLastRealOrderId($order->getIncrementId())
                                        ->setLastOrderStatus($order->getStatus());
                }

                $responseContent['order_id'] = true;
            }
        }
        else
        {
            if(empty($quote_id) === false)
            {
                //set the cache to stop notify processing
                $this->cache->save("started", "quote_Front_processing_$quote_id", ["cashfree"], 30);

                $this->logger->info("Cashfree front-end order processing started quoteID:" . $quote_id);

                $responseContent = [
                    'success'   => false,
                    'parameters' => []
                ];
            }
            else
            {
                $this->logger->info("Cashfree order already processed with quoteID:" . $this->checkoutSession
                        ->getLastQuoteId());

                $responseContent = [
                    'success'    => true,
                    'order_id'   => true,
                    'parameters' => []
                ];

            }
        }

        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $response->setData($responseContent);
        $response->setHttpResponseCode(200);

        return $response;
    }
}