<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration\Services;

use Sprii\LiveShoppingIntegration\SpriiLiveShoppingIntegration;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

class SpriiOrderService extends AbstractApiService
{
    /**
     * @param OrderEntity $order
     * @return void
     */
    public function sendOrderUpdate(OrderEntity $order): bool
    {
        try {
            $this->callApiAsync(
                'POST',
                '/webhook',
                $this->buildSpriiOrder($order)
            )->wait();

	    return true;
        } catch (GuzzleException | Exception $e) {
            $this->logger->error(
                'Sprii: Error happened sending order update (' . $order->getOrderNumber() . ')',
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => json_encode($this->buildSpriiOrder($order))
                ]
            );

	    return false;
        }
    }

    /**
     * @param OrderEntity $order
     * @return array[]
     */
    protected function buildSpriiOrder(OrderEntity $order): array
    {
        $spriiCartId = $order->getCustomFields()[SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID];

        return [
            'orders' => [
                'id' => $order->getOrderNumber(),
                'sprii_reference' => $spriiCartId,
                'time' => strtotime($order->getCreatedAt()->format('d')),
                'products' => $this->buildSpriiProducts($order->getLineItems())
            ]
        ];
    }

    /**
     * @param OrderLineItemCollection $lineItems
     * @return array
     */
    protected function buildSpriiProducts(OrderLineItemCollection $lineItems): array
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
