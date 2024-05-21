<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration\Subscribers;

use Sprii\LiveShoppingIntegration\Services\SpriiCartService;
use Exception;
use Monolog\Logger;
use Shopware\Core\Checkout\Cart\CartEvents;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sprii\LiveShoppingIntegration\SpriiLiveShoppingIntegration;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;

class SpriiCartSubscriber implements EventSubscriberInterface
{
    protected SpriiCartService $spriiCartService;
    protected Logger $logger;

    public function __construct(SpriiCartService $spriiCartService, Logger $logger)
    {
        $this->spriiCartService = $spriiCartService;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'onCartConverted'
        ];
    }

    public function onCartConverted(CartConvertedEvent $event)
    {
        try {
            // If it is not a cart from Sprii, return early
            if (empty($event->getCart()->getExtensions()[SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID])) {
                return;
            }

            // Get the saved Sprii reference id from the cart
            $spriiRefId = (string) $event
                ->getCart()
                ->getExtensions()[SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID];

            $convertedCart = $event->getConvertedCart();

            // Ensure we save the original Sprii reference id on the converted cart/order
            $convertedCart['customFields'][SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID] = $spriiRefId;

            $event->setConvertedCart($convertedCart);
        } catch (Exception | \Doctrine\DBAL\Driver\Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        }
    }
}
