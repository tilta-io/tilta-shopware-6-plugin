<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Tilta\Sdk\Exception\Validation\InvalidFieldValueException;
use Tilta\Sdk\Model\Request\Order\CreateOrderRequestModel;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\Event\CreateOrderRequestModelBuiltEvent;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;

class CreateOrderRequestModelFactory
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AddressModelFactory $addressModelFactory,
        private readonly AmountModelFactory $amountModelFactory,
        private readonly LineItemsFactory $lineItemsFactory
    ) {
    }

    /**
     * @throws InvalidFieldValueException
     */
    public function createModel(OrderEntity $orderEntity, string $tiltaPaymentMethod, string $tiltaPaymentTerm, string $buyerExternalId, Context $context): CreateOrderRequestModel
    {
        $orderRequestModel = new CreateOrderRequestModel();

        $orderRequestModel
            ->setMerchantExternalId($this->configService->getMerchantExternalId())
            ->setBuyerExternalId($buyerExternalId)
            ->setPaymentMethod($tiltaPaymentMethod)
            ->setPaymentTerm($tiltaPaymentTerm)
            ->setOrderedAt($orderEntity->getCreatedAt())
            ->setOrderExternalId($orderEntity->getOrderNumber())
            ->setAmount($this->amountModelFactory->createAmountForOrder($orderEntity, $context));

        if (!$orderEntity->getDeliveries() instanceof OrderDeliveryCollection) {
            throw new RuntimeException('oder deliveries has to be loaded');
        }

        $deliveryEntity = $orderEntity->getDeliveries()->first();
        $deliveryAddressEntity = $deliveryEntity instanceof OrderDeliveryEntity ? $deliveryEntity->getShippingOrderAddress() : null;
        if ($deliveryAddressEntity instanceof OrderAddressEntity) {
            $orderRequestModel->setDeliveryAddress(
                $this->addressModelFactory->createFromOrderAddress($deliveryAddressEntity, $context)
            );
        }

        $orderRequestModel->setLineItems($this->lineItemsFactory->createFromOrder($orderEntity, $orderRequestModel->getAmount()->getCurrency()));

        /** @var CreateOrderRequestModelBuiltEvent $event */
        $event = $this->eventDispatcher->dispatch(new CreateOrderRequestModelBuiltEvent($orderEntity, $orderRequestModel));

        return $event->getModel();
    }
}
