<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\StateMachine\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;
use Tilta\TiltaPaymentSW6\Core\StateMachine\Exception\InvoiceNumberMissingException;
use Tilta\TiltaPaymentSW6\Core\Util\OrderHelper;
use Tilta\TiltaPaymentSW6\Core\Util\PaymentMethodHelper;

class StateMachineRegistryDecorator extends StateMachineRegistry // we must extend it, cause there is no interface
{
    protected ConfigService $configService;

    protected EntityRepository $orderRepository;

    protected EntityRepository $orderDeliveryRepository;

    private StateMachineRegistry $innerService;

    private OrderHelper $orderHelper;

    public function __construct(
        StateMachineRegistry $innerService,
        ConfigService $configService,
        OrderHelper $orderHelper,
        EntityRepository $orderRepository,
        EntityRepository $orderDeliveryRepository
    ) {
        $this->innerService = $innerService;
        $this->configService = $configService;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->orderHelper = $orderHelper;
    }

    public function transition(Transition $transition, Context $context): StateMachineStateCollection
    {
        if ($this->configService->isStateWatchingEnabled()
            && $this->configService->getStateForShip() !== null
            && $transition->getEntityName() === OrderDeliveryDefinition::ENTITY_NAME
        ) {
            /** @var OrderDeliveryEntity $orderDelivery */
            $orderDelivery = $this->orderDeliveryRepository->search(new Criteria([$transition->getEntityId()]), $context)->first();
            $order = $this->getOrder($orderDelivery->getOrderId(), $context);

            $transaction = $order instanceof OrderEntity && $order->getTransactions() instanceof OrderTransactionCollection ? $order->getTransactions()->first() : null;
            $paymentMethod = $transaction instanceof OrderTransactionEntity ? $transaction->getPaymentMethod() : null;
            if ($order instanceof OrderEntity && $paymentMethod instanceof PaymentMethodEntity
                && PaymentMethodHelper::isTiltaPaymentMethod($paymentMethod)
                && $this->orderHelper->getInvoiceNumberAndExternalId($order) === null
            ) {
                throw new InvoiceNumberMissingException();
            }
        }

        return $this->innerService->transition($transition, $context);
    }

    /**
     * @deprecated method has been removed from shopware core
     */
    public function getInitialState(string $stateMachineName, Context $context): StateMachineStateEntity
    {
        /** @phpstan-ignore-next-line */
        return $this->innerService->getInitialState($stateMachineName, $context);
    }

    public function getAvailableTransitions(string $entityName, string $entityId, string $stateFieldName, Context $context): array
    {
        return $this->innerService->getAvailableTransitions($entityName, $entityId, $stateFieldName, $context);
    }

    public function getStateMachine(string $name, Context $context): StateMachineEntity
    {
        return $this->innerService->getStateMachine($name, $context);
    }

    protected function getOrder(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('documents.documentType');

        return $this->orderRepository->search($criteria, $context)->first();
    }
}
