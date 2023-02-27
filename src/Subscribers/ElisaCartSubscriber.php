<?php declare(strict_types=1);

namespace Elisa\LiveShoppingIntegration\Subscribers;

use Elisa\LiveShoppingIntegration\Services\ElisaCartService;
use Exception;
use Monolog\Logger;
use Shopware\Core\Checkout\Cart\CartEvents;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Event\CartCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Elisa\LiveShoppingIntegration\ElisaLiveShoppingIntegration;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;

class ElisaCartSubscriber implements EventSubscriberInterface
{
    protected ElisaCartService $elisaCartService;
    protected Logger $logger;

    public function __construct(ElisaCartService $elisaCartService, Logger $logger)
    {
        $this->elisaCartService = $elisaCartService;
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
            // If it is not a cart from Elisa, return early
            if (empty($event->getCart()->getExtensions()[ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID])) {
                return;
            }

            // Get the saved Elisa reference id from the cart
            $elisaRefId = (string)$event
                ->getCart()
                ->getExtensions()[ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID];

            $convertedCart = $event->getConvertedCart();

            // Ensure we save the original Elisa reference id on the converted cart/order
            $convertedCart['customFields'][ElisaLiveShoppingIntegration::ELISA_CART_REFERENCE_ID] = $elisaRefId;

            $event->setConvertedCart($convertedCart);
        } catch (Exception|\Doctrine\DBAL\Driver\Exception $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        }
    }
}
