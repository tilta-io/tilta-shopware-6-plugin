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
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Tilta\Sdk\Exception\Validation\InvalidFieldValueException;
use Tilta\Sdk\Model\Address;
use Tilta\Sdk\Model\Amount;
use Tilta\Sdk\Model\Order\LineItem;
use Tilta\Sdk\Model\Request\Order\CreateOrderRequestModel;
use Tilta\Sdk\Util\AddressHelper;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\Event\CreateOrderRequestModelBuiltEvent;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;
use Tilta\TiltaPaymentSW6\Core\Util\AmountHelper;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;

class CreateOrderRequestModelFactory
{
    private ConfigService $configService;

    private EventDispatcherInterface $eventDispatcher;

    private EntityHelper $entityHelper;

    public function __construct(
        ConfigService $configService,
        EventDispatcherInterface $eventDispatcher,
        EntityHelper $entityHelper
    ) {
        $this->configService = $configService;
        $this->eventDispatcher = $eventDispatcher;
        $this->entityHelper = $entityHelper;
    }

    /**
     * @throws InvalidFieldValueException
     */
    public function createModel(OrderEntity $orderEntity, string $tiltaPaymentMethod, string $buyerExternalId): CreateOrderRequestModel
    {
        $orderRequestModel = new CreateOrderRequestModel();

        $orderRequestModel
            ->setMerchantExternalId($this->configService->getMerchantExternalId())
            ->setBuyerExternalId($buyerExternalId)
            ->setPaymentMethod($tiltaPaymentMethod)
            ->setOrderedAt($orderEntity->getCreatedAt())
            ->setOrderExternalId($orderEntity->getOrderNumber())
            ->setAmount(
                (new Amount())
                    ->setNet(AmountHelper::toSdk($orderEntity->getAmountNet()))
                    ->setGross(AmountHelper::toSdk($orderEntity->getAmountTotal()))
                    ->setTax(AmountHelper::toSdk($orderEntity->getPrice()->getCalculatedTaxes()->getAmount()))
                    ->setCurrency($this->entityHelper->getCurrencyCode($orderEntity))
            );

        if (!$orderEntity->getDeliveries() instanceof OrderDeliveryCollection) {
            throw new RuntimeException('oder deliveries has to be loaded');
        }

        $deliveryEntity = $orderEntity->getDeliveries()->first();
        $deliveryAddressEntity = $deliveryEntity instanceof OrderDeliveryEntity ? $deliveryEntity->getShippingOrderAddress() : null;
        if ($deliveryAddressEntity instanceof OrderAddressEntity) {
            $orderRequestModel->setDeliveryAddress(
                (new Address())
                    ->setStreet(AddressHelper::getStreetName($deliveryAddressEntity->getStreet()))
                    ->setHouseNumber(AddressHelper::getHouseNumber($deliveryAddressEntity->getStreet()))
                    ->setPostcode($deliveryAddressEntity->getZipcode())
                    ->setCity($deliveryAddressEntity->getCity())
                    ->setCountry($this->entityHelper->getCountryCode($deliveryAddressEntity))
                    ->setAdditional(CustomerAddressHelper::mergeAdditionalAddressLines($deliveryAddressEntity))
            );
        }

        if (!$orderEntity->getLineItems() instanceof OrderLineItemCollection) {
            throw new RuntimeException('oder items has to be loaded');
        }

        $lineItems = [];
        foreach ($orderEntity->getLineItems() as $lineItem) {
            $lineItems[] = (new LineItem())
                ->setName($lineItem->getLabel())
                ->setQuantity($lineItem->getQuantity())
                ->setPrice(AmountHelper::toSdk($lineItem->getPrice() instanceof CalculatedPrice ? $lineItem->getPrice()->getUnitPrice() : 0))
                ->setCurrency($orderRequestModel->getAmount()->getCurrency())
                ->setCategory('') // TODO should this also be send?
            ;
        }

        $orderRequestModel->setLineItems($lineItems);

        /** @var CreateOrderRequestModelBuiltEvent $event */
        $event = $this->eventDispatcher->dispatch(new CreateOrderRequestModelBuiltEvent($orderEntity, $orderRequestModel));

        return $event->getModel();
    }
}
