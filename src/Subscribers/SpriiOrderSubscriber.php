<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration\Subscribers;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sprii\LiveShoppingIntegration\SpriiLiveShoppingIntegration;
use Sprii\LiveShoppingIntegration\Services\SpriiOrderService;
use GuzzleHttp\Client;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;

class SpriiOrderSubscriber implements EventSubscriberInterface
{
    protected SpriiOrderService $spriiOrderService;

    public function __construct(SpriiOrderService $spriiOrderService)
    {
        $this->spriiOrderService = $spriiOrderService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced'
        ];
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        if (empty($event->getOrder()->getCustomFields()[SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID])) {
            return;
        }

        $this->spriiOrderService->sendOrderUpdate($event->getOrder());
    }
}
