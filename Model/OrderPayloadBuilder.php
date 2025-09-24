<?php
namespace TextYess\Integration\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Framework\UrlInterface;

class OrderPayloadBuilder
{
    protected UrlInterface $urlBuilder;

    public function __construct(UrlInterface $urlBuilder)
    {
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Build a full order payload compatible with OrderExtendedDto
     *
     * @param OrderInterface $order
     * @param array $extra Extra data to merge (e.g. fulfillments)
     * @return array
     */
    public function build(OrderInterface $order, array $extra = []): array
    {
        $billing  = $order->getBillingAddress();
        $shipping = $order->getShippingAddress();

        $payload = [
            'id'              => (string)$order->getIncrementId(),
            'createdAt'       => $order->getCreatedAt() ? date('c', strtotime($order->getCreatedAt())) : '',
            'updatedAt'       => $order->getUpdatedAt() ? date('c', strtotime($order->getUpdatedAt())) : '',
            'total'           => (float)$order->getGrandTotal(),
            'currency'        => $order->getOrderCurrencyCode(),
            'status'          => $this->mapFinancialStatus($order->getState()),

            'subtotal'        => (float)$order->getSubtotal(),
            'totalTax'        => (float)$order->getTaxAmount(),
            'totalDiscount'   => (float)$order->getDiscountAmount(),
            'totalShipping'   => (float)$order->getShippingAmount(),

            'discountCodes'   => [], // Magento does not store codes natively, add if needed
            'tags'            => [],

            'customer' => [
                'id'         => (string)($order->getCustomerId() ?: $order->getCustomerEmail()),
                'email'      => (string)$order->getCustomerEmail(),
                'firstName'  => $order->getCustomerFirstname() ?: ($billing ? $billing->getFirstname() : ''),
                'lastName'   => $order->getCustomerLastname() ?: ($billing ? $billing->getLastname() : ''),
                'phone'      => $billing ? $billing->getTelephone() : '',
            ],

            'billingAddress'  => $billing ? $this->mapAddress($billing) : [],
            'shippingAddress' => $shipping ? $this->mapAddress($shipping) : [],

            'lineItems' => array_map(function ($item) {
                $rowTotal   = (float)$item->getRowTotal();
                $discount   = (float)$item->getDiscountAmount();
                $tax        = (float)$item->getTaxAmount();

                return [
                    'id'           => (string)$item->getId() ?: (string)$item->getProductId(),
                    'productId'    => (string)$item->getProductId(),
                    'sku'          => $item->getSku(),
                    'title'        => $item->getName(),
                    'variantTitle' => implode(' / ', array_column($item->getProductOptions()['attributes_info'] ?? [], 'value')),
                    'quantity'     => (int)$item->getQtyOrdered(),
                    'price'        => round((float)$item->getPrice(), 2),
                    'total'        => round($rowTotal, 2),
                    'discount'     => $discount,
                    'tax'          => $tax,
                ];
            }, $order->getAllVisibleItems()),

            'shippingLines' => $order->getShippingDescription()
                ? [[
                    'title' => $order->getShippingDescription(),
                    'price' => (float)$order->getShippingAmount(),
                    'code'  => $order->getShippingMethod(),
                ]]
                : [],

            'paymentMethods' => $order->getPayment()
                ? [(string)$order->getPayment()->getMethodInstance()->getTitle()]
                : [],

            'fulfillments' => [],
        ];

        return array_merge($payload, $extra);
    }


    private function mapAddress($address): array
    {
        $street = $address->getStreet();

        // Country might be missing or invalid, so guard it
        $countryModel = $address->getCountryModel();
        $countryName  = $countryModel ? $countryModel->getName() : ($address->getCountryId() ?: '');

        return [
            'firstName'    => $address->getFirstname() ?? '',
            'lastName'     => $address->getLastname() ?? '',
            'company'      => $address->getCompany() ?? '',
            'address1'     => $street[0] ?? '',
            'address2'     => $street[1] ?? '',
            'city'         => $address->getCity() ?? '',
            'province'     => $address->getRegion() ?? '',
            'provinceCode' => $address->getRegionCode() ?? '',
            'country'      => $countryName,
            'countryCode'  => $address->getCountryId() ?? '',
            'zip'          => $address->getPostcode() ?? '',
            'phone'        => $address->getTelephone() ?? '',
        ];
    }


    private function mapFinancialStatus(string $state): string
    {
        $map = [
            'new'              => 'created',
            'pending_payment'  => 'created',
            'processing'       => 'paid',
            'complete'         => 'paid',
            'closed'           => 'refunded',
            'canceled'         => 'voided',
        ];
        return $map[$state] ?? 'created';
    }

    public function buildFulfillment(ShipmentInterface $shipment): array
    {
        $tracks = [];

        foreach ($shipment->getAllTracks() as $track) {
            $carrierCode     = strtolower((string)$track->getCarrierCode());
            $trackingNumber  = trim((string)$track->getTrackNumber());
            $title           = trim((string)$track->getTitle());

            // Skip if tracking number is missing
            if ($trackingNumber === '') {
                continue;
            }

            // Prefer Magento-provided URL if available
            $url = '';
            if (method_exists($track, 'getUrl')) {
                $url = (string)$track->getUrl();
            }

            // Fallback URLs for known carriers
            if ($url === '') {
                switch (true) {
                    case str_contains($carrierCode, 'dhl'):
                        $url = "https://www.dhl.com/global-en/home/tracking/tracking-express.html?submit=1&tracking-id=" . urlencode($trackingNumber);
                        break;
                    case str_contains($carrierCode, 'ups'):
                        $url = "https://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=" . urlencode($trackingNumber);
                        break;
                    case str_contains($carrierCode, 'fedex'):
                        $url = "https://www.fedex.com/fedextrack/?tracknumbers=" . urlencode($trackingNumber);
                        break;
                    case str_contains($carrierCode, 'usps'):
                        $url = "https://tools.usps.com/go/TrackConfirmAction?tLabels=" . urlencode($trackingNumber);
                        break;
                }
            }

            $tracks[] = [
                'id'               => (string)$track->getEntityId(),
                'tracking_company' => $title ?: strtoupper($carrierCode),
                'tracking_url'     => $url,
            ];
        }

        // Safely get first track or empty defaults
        $firstTrack = $tracks[0] ?? ['tracking_company' => '', 'tracking_url' => ''];

        return [
            'id'               => (string)$shipment->getIncrementId(),
            'shipment_status'  => 'shipped',
            'tracking_company' => $firstTrack['tracking_company'],
            'tracking_url'     => $firstTrack['tracking_url'],
            // filter out any empty URLs
            'tracking_urls'    => array_values(array_filter(array_column($tracks, 'tracking_url'))),
        ];
    }



}
