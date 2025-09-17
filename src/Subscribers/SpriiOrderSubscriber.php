<?php declare(strict_types=1);

namespace Sprii\LiveShoppingIntegration\Subscribers;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Sprii\LiveShoppingIntegration\SpriiLiveShoppingIntegration;
use Sprii\LiveShoppingIntegration\Services\SpriiOrderService;
use GuzzleHttp\Client;

class SpriiOrderSubscriber implements EventSubscriberInterface
{
    public function __construct(
	protected SpriiOrderService $spriiOrderService,
	protected EntityRepository $orderRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onStateMachineTransitionEvent'
        ];
    }

    public function onStateMachineTransitionEvent(StateMachineTransitionEvent $event)
    {
        $context = $event->getContext();
        $eventName = $event->getToPlace()->getTechnicalName();
        $relevantEvent = in_array(
            $eventName,
            [
                OrderTransactionStates::STATE_AUTHORIZED,
                OrderTransactionStates::STATE_PAID
            ]
        );
        // Do not waste compute time fetching order if the event should not be handled anyway
        if (!$relevantEvent) {
            return;
        }

        $transactionId = $event->getEntityId();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('transactions.id', $transactionId));
	$criteria->addAssociation('lineItems');

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        // If order doesn't have a referenceId or already is exported. We skip it.
        if (empty($order->getCustomFields()[SpriiLiveShoppingIntegration::SPRII_CART_REFERENCE_ID]) ||
            (isset($order->getCustomFields()[SpriiLiveShoppingIntegration::SPRII_CART_EXPORTED]) &&
            $order->getCustomFields()[SpriiLiveShoppingIntegration::SPRII_CART_EXPORTED])
        ) {
            return;
        }


        $success = $this->spriiOrderService->sendOrderUpdate($order);

        $this->updateOrderCustomFields($order, $success, $context);
    }

    public function updateOrderCustomFields(OrderEntity $order, bool $success, Context $context): void
    {
        $customFields = $order->getCustomFields();
        $customFields[SpriiLiveShoppingIntegration::SPRII_CART_EXPORTED] = $success;

        $this->orderRepository->update(
            [
                [
                    'id' => $order->getId(),
                    'customFields' => $customFields
                ]
            ],
            $context
        );
    }

}
