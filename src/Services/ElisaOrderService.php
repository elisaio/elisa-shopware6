<?php declare(strict_types=1);

namespace Elisa\LiveShoppingIntegration\Services;

use Elisa\LiveShoppingIntegration\ElisaLiveShoppingIntegration;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

class ElisaOrderService extends AbstractApiService
{
    /**
     * @param OrderEntity $order
     * @return void
     */
    public function sendOrderUpdate(OrderEntity $order): void
    {
        try {
            $this->callApiAsync(
                'POST',
                '/webhook',
                $this->buildElisaOrder($order)
            )->wait();
        } catch (GuzzleException|Exception $e) {
            $this->logger->error(
                'Elisa: Error happened sending order update (' . $order->getOrderNumber() . ')',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => json_encode($this->buildElisaOrder($order))
                ]
            );
        }
    }

    /**
     * @param OrderEntity $order
     * @return array[]
     */
    protected function buildElisaOrder(OrderEntity $order): array
    {
        $elisaCartId = $order->getCustomFields()[ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID];

        return [
            'orders' => [
                'id' => $order->getOrderNumber(),
                'elisa_reference' => $elisaCartId,
                'time' => strtotime($order->getCreatedAt()->format('d')),
                'products' => $this->buildElisaProducts($order->getLineItems())
            ]
        ];
    }

    /**
     * @param OrderLineItemCollection $lineItems
     * @return array
     */
    protected function buildElisaProducts(OrderLineItemCollection $lineItems): array
    {
        $products = [];

        foreach ($lineItems as $lineItem) {
            $products[] = [
                'id' => $lineItem->getProductId(),
                'qty' => $lineItem->getQuantity(),
                'amount' => $lineItem->getTotalPrice()
            ];
        }

        return $products;
    }
}
