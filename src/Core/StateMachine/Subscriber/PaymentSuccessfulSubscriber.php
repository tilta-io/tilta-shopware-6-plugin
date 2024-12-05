<?php

/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\StateMachine\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tilta\TiltaPaymentSW6\Core\Event\TiltaPaymentSuccessfulEvent;

class PaymentSuccessfulSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TiltaPaymentSuccessfulEvent::class => ['changeTransactionState', 6000],
        ];
    }

    public function changeTransactionState(TiltaPaymentSuccessfulEvent $event): void
    {
        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $event->getOrderTransactionEntity()->getId(),
                StateMachineTransitionActions::ACTION_AUTHORIZE,
                'stateId'
            ),
            $event->getSalesChannelContext()->getContext()
        );
    }
}
