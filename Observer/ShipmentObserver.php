<?php
namespace TextYess\Integration\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use TextYess\Integration\Model\OrderPayloadBuilder;
use TextYess\Integration\Model\WebhookNotifier;
use TextYess\Integration\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Observer for sales_order_shipment_save_after event
 *
 * This class listens for shipment creation events and triggers
 * the TextYess "order.fulfilled" webhook with the fulfillment payload.
 */
class ShipmentObserver implements ObserverInterface
{
    protected WebhookNotifier $notifier;
    protected OrderPayloadBuilder $payloadBuilder;
    protected LoggerInterface $logger;
    protected Config $config;

    /**
     * @param WebhookNotifier $notifier       Handles sending data to TextYess webhook endpoint
     * @param OrderPayloadBuilder $payloadBuilder Builds the order + fulfillment payload
     * @param LoggerInterface $logger         PSR-3 logger instance for debug logging
     * @param Config $config                  Module configuration provider
     */
    public function __construct(
        WebhookNotifier $notifier,
        OrderPayloadBuilder $payloadBuilder,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->notifier = $notifier;
        $this->payloadBuilder = $payloadBuilder;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * Executes when a shipment is created or saved.
     *
     * Builds a fulfillment payload and sends it to the TextYess webhook.
     * Logs payload details only if debug logging is enabled in configuration.
     *
     * @param Observer $observer Magento event observer containing the Shipment
     * @return void
     */
    public function execute(Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        if (!$shipment) {
            return;
        }

        $order = $shipment->getOrder();

        // Build fulfillment object and merge into order payload
        $fulfillment = $this->payloadBuilder->buildFulfillment($shipment);
        $payload = $this->payloadBuilder->build($order, [
            'fulfillments' => [$fulfillment]
        ]);

        // Log payload only if debug is enabled
        if ($this->config->isLogEnabled()) {
            $this->logger->info('[TextYess] Prepared order.fulfilled payload', ['payload' => $payload]);
        }

        // Send webhook (errors are handled and logged inside WebhookNotifier)
        $this->notifier->send('orders/fulfilled', $payload, 'fulfilled');
    }
}
