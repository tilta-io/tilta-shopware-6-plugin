<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\StateMachine\Subscriber;

use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tilta\TiltaPaymentSW6\Core\Components\Api\OperationService;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderDataEntity;
use Tilta\TiltaPaymentSW6\Core\Extension\OrderDataEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;

class TransitionSubscriber implements EventSubscriberInterface
{
    private ConfigService $configService;

    /**
     * @var EntityRepository<EntityCollection<OrderDeliveryEntity>>
     */
    private EntityRepository $orderDeliveryRepository;

    /**
     * @var EntityRepository<EntityCollection<OrderEntity>>
     */
    private EntityRepository $orderRepository;

    private OperationService $operationService;

    /**
     * @param EntityRepository<EntityCollection<OrderDeliveryEntity>> $orderDeliveryRepository
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        EntityRepository $orderDeliveryRepository,
        EntityRepository $orderRepository,
        ConfigService $configService,
        OperationService $operationService
    ) {
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->orderRepository = $orderRepository;
        $this->configService = $configService;
        $this->operationService = $operationService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onTransition',
        ];
    }

    public function onTransition(StateMachineTransitionEvent $event): void
    {
        if (!$this->configService->isStateWatchingEnabled()) {
            return;
        }

        if ($event->getEntityName() === OrderDeliveryDefinition::ENTITY_NAME) {
            /** @var OrderDeliveryEntity $orderDelivery */
            $orderDelivery = $this->orderDeliveryRepository->search(new Criteria([$event->getEntityId()]), $event->getContext())->first();
            $order = $this->getOrder($orderDelivery->getOrderId(), $event->getContext());
        } elseif ($event->getEntityName() === OrderDefinition::ENTITY_NAME) {
            $order = $this->getOrder($event->getEntityId(), $event->getContext());
        } else {
            return;
        }

        /** @var TiltaOrderDataEntity|null $tiltaData */
        $tiltaData = $order->getExtension(OrderDataEntityExtension::EXTENSION_NAME);
        if (!$tiltaData instanceof TiltaOrderDataEntity) {
            // this is not a tilta order - or if it is, the order data is broken
            return;
        }

        switch ($event->getToPlace()->getTechnicalName()) {
            case $this->configService->getStateForShip():
                $this->operationService->createInvoice($order, $event->getContext());
                break;
            case $this->configService->getStateCancel():
                $this->operationService->cancelOrder($order, $event->getContext());
                break;
            case $this->configService->getStateReturn():
                $this->operationService->refundInvoice($order, $event->getContext());
                break;
        }
    }

    protected function getOrder(string $orderId, Context $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('documents.documentType');
        $criteria->addAssociation('lineItems');

        $orderEntity = $this->orderRepository->search($criteria, $context)->first();

        if (!$orderEntity instanceof OrderEntity) {
            throw new RuntimeException('Order seems to be got deleted during transition');
        }

        return $orderEntity;
    }
}
