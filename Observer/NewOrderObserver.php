<?php
namespace TextYess\Integration\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;
use TextYess\Integration\Model\OrderPayloadBuilder;
use TextYess\Integration\Model\WebhookNotifier;
use TextYess\Integration\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Observer for sales_order_place_after event
 *
 * This observer is triggered after a new order is placed.
 * It builds the order payload and sends it to the TextYess "order.created" webhook.
 */
class NewOrderObserver implements ObserverInterface
{
    protected WebhookNotifier $notifier;
    protected OrderRepositoryInterface $orderRepository;
    protected OrderPayloadBuilder $payloadBuilder;
    protected LoggerInterface $logger;
    protected Config $config;

    /**
     * @param WebhookNotifier $notifier          Handles sending data to the TextYess webhook
     * @param OrderRepositoryInterface $orderRepository Used to fetch order data if needed
     * @param OrderPayloadBuilder $payloadBuilder Builds the order payload array
     * @param LoggerInterface $logger            PSR-3 logger instance for debugging
     * @param Config $config                     Module configuration provider
     */
    public function __construct(
        WebhookNotifier $notifier,
        OrderRepositoryInterface $orderRepository,
        OrderPayloadBuilder $payloadBuilder,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->notifier = $notifier;
        $this->orderRepository = $orderRepository;
        $this->payloadBuilder = $payloadBuilder;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Executes when a new order is placed.
     *
     * Builds a payload for the "orders/create" webhook and sends it.
     * Payload is logged only if debug logging is enabled in configuration.
     *
     * @param Observer $observer Magento event observer containing the Order
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order || !$order->getId()) {
            return;
        }

        // Fire only for newly created orders (ignore updates)
        if ($order->getOrigData('entity_id')) {
            return;
        }

        $payload = $this->payloadBuilder->build($order);

        // Log payload BEFORE sending (only if debug enabled)
        if ($this->config->isLogEnabled()) {
            $this->logger->debug('[TextYess] Prepared order.created payload', ['payload' => $payload]);
        }

        // Send webhook (WebhookNotifier handles its own logging)
        $this->notifier->send('orders/create', $payload, 'create');
    }
}
