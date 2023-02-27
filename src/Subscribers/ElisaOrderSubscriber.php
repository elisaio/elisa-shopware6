<?php declare(strict_types=1);

namespace Elisa\LiveShoppingIntegration\Subscribers;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Elisa\LiveShoppingIntegration\ElisaLiveShoppingIntegration;
use Elisa\LiveShoppingIntegration\Services\ElisaOrderService;
use GuzzleHttp\Client;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;

class ElisaOrderSubscriber implements EventSubscriberInterface
{
    protected ElisaOrderService $elisaOrderService;

    public function __construct(ElisaOrderService $elisaOrderService)
    {
        $this->elisaOrderService = $elisaOrderService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced'
        ];
    }

    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event)
    {
        if (empty($event->getOrder()->getCustomFields()[ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID])) {
            return;
        }

        $this->elisaOrderService->sendOrderUpdate($event->getOrder());
    }
}
