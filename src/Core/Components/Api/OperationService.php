<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Components\Api;

use DateTime;
use Exception;
use Monolog\Logger;
use RuntimeException;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Request\CreditNote\CreateCreditNoteRequestModel;
use Tilta\Sdk\Model\Request\Invoice\CreateInvoiceRequestModel;
use Tilta\Sdk\Model\Request\Order\CancelOrderRequestModel;
use Tilta\Sdk\Model\Request\Order\GetOrderDetailsRequestModel;
use Tilta\Sdk\Service\Request\CreditNote\CreateCreditNoteRequest;
use Tilta\Sdk\Service\Request\Invoice\CreateInvoiceRequest;
use Tilta\Sdk\Service\Request\Order\CancelOrderRequest;
use Tilta\Sdk\Service\Request\Order\GetOrderDetailsRequest;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\AddressModelFactory;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\AmountModelFactory;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\LineItemsFactory;
use Tilta\TiltaPaymentSW6\Core\Exception\OrderIsNotATiltaOrder;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderDataEntity;
use Tilta\TiltaPaymentSW6\Core\Extension\OrderDataEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;
use Tilta\TiltaPaymentSW6\Core\Util\OrderHelper;

/**
 * @internal please always use the state-machine to change the state of the order. If you know what you are doing, keep going
 */
class OperationService
{
    private CreateInvoiceRequest $createInvoiceRequest;

    private GetOrderDetailsRequest $orderDetailsRequest;

    private Logger $logger;

    /**
     * @var EntityRepository<EntityCollection<TiltaOrderDataEntity>>
     */
    private EntityRepository $tiltaOrderDataRepository;

    private OrderHelper $orderHelper;

    private EntityHelper $entityHelper;

    private AmountModelFactory $amountModelFactory;

    private AddressModelFactory $addressModelFactory;

    private LineItemsFactory $lineItemsFactory;

    private CreateCreditNoteRequest $createCreditNoteRequest;

    private CancelOrderRequest $cancelOrderRequest;

    /**
     * @param EntityRepository<EntityCollection<TiltaOrderDataEntity>> $tiltaOrderDataRepository
     */
    public function __construct(
        CreateInvoiceRequest $createInvoiceRequest,
        GetOrderDetailsRequest $orderDetailsRequest,
        CreateCreditNoteRequest $createCreditNoteRequest,
        CancelOrderRequest $cancelOrderRequest,
        EntityRepository $tiltaOrderDataRepository,
        OrderHelper $orderHelper,
        EntityHelper $entityHelper,
        AmountModelFactory $amountModelFactory,
        AddressModelFactory $addressModelFactory,
        LineItemsFactory $lineItemsFactory,
        Logger $logger
    ) {
        $this->createInvoiceRequest = $createInvoiceRequest;
        $this->createCreditNoteRequest = $createCreditNoteRequest;
        $this->logger = $logger;
        $this->tiltaOrderDataRepository = $tiltaOrderDataRepository;
        $this->orderHelper = $orderHelper;
        $this->entityHelper = $entityHelper;
        $this->amountModelFactory = $amountModelFactory;
        $this->addressModelFactory = $addressModelFactory;
        $this->lineItemsFactory = $lineItemsFactory;
        $this->orderDetailsRequest = $orderDetailsRequest;
        $this->cancelOrderRequest = $cancelOrderRequest;
    }

    public function createInvoice(OrderEntity $orderEntity, Context $context): bool
    {
        [$invoiceNumber, $invoiceExternalId] = $this->orderHelper->getInvoiceNumberAndExternalId($orderEntity, $context) ?: [];

        $billingAddress = $orderEntity->getBillingAddress() ?? $this->entityHelper->getOrderAddress($orderEntity->getBillingAddressId(), $context);

        if (!$billingAddress instanceof OrderAddressEntity) {
            throw new RuntimeException('Order address was not found.');
        }

        if ($orderEntity->getOrderNumber() === null) {
            throw new RuntimeException(sprintf('Order with ID %s does not have a order-number', $orderEntity->getId()));
        }

        $data = (new CreateInvoiceRequestModel())
            ->setOrderExternalIds([$orderEntity->getOrderNumber()])
            ->setInvoiceNumber($invoiceNumber)
            ->setInvoiceExternalId($invoiceExternalId)
            ->setBillingAddress($this->addressModelFactory->createFromOrderAddress($billingAddress, $context))
            ->setAmount($this->amountModelFactory->createAmountForOrder($orderEntity, $context))
            ->setInvoicedAt(new DateTime()); // TODO should this saved/provided separately?

        $data->setLineItems($this->lineItemsFactory->createFromOrder($orderEntity, $data->getAmount()->getCurrency()));

        try {
            $invoiceResponse = $this->createInvoiceRequest->execute($data);
            sleep(1); // we have to wait one second, so the invoice has been published into the internal systems of tilta.

            $this->updateTransactionStatus($context, $orderEntity, [
                TiltaOrderDataEntity::FIELD_INVOICE_NUMBER => $invoiceResponse->getInvoiceNumber(),
                TiltaOrderDataEntity::FIELD_INVOICE_EXTERNAL_ID => $invoiceResponse->getInvoiceExternalId(),
            ]);

            return true;
        } catch (TiltaException $tiltaException) {
            $this->logger->critical(
                'Exception during create invoice. (Exception: ' . $tiltaException->getMessage() . ')',
                [
                    'error' => $tiltaException->getTiltaCode(),
                    'order' => $orderEntity->getId(),
                    'order_external_id' => implode(',', $data->getOrderExternalIds()),
                ]
            );
        }

        return false;
    }

    public function refundInvoice(OrderEntity $orderEntity, Context $context): bool
    {
        [$invoiceNumber, $invoiceExternalId] = $this->orderHelper->getInvoiceNumberAndExternalId($orderEntity, $context) ?: [];

        if ($invoiceExternalId === null) {
            throw new RuntimeException('order has not been invoiced yet, or external-invoice-id has been deleted.');
        }

        $billingAddress = $orderEntity->getBillingAddress() ?? $this->entityHelper->getOrderAddress($orderEntity->getBillingAddressId(), $context);

        if (!$billingAddress instanceof OrderAddressEntity) {
            throw new RuntimeException('Order address was not found.');
        }

        if ($orderEntity->getOrderNumber() === null) {
            throw new RuntimeException(sprintf('Order with ID %s does not have a order-number', $orderEntity->getId()));
        }

        /** @var TiltaOrderDataEntity $tiltaData */
        $tiltaData = $orderEntity->getExtension(OrderDataEntityExtension::EXTENSION_NAME);

        $data = (new CreateCreditNoteRequestModel())
            ->setBuyerExternalId($tiltaData->getBuyerExternalId())
            ->setOrderExternalIds([$orderEntity->getOrderNumber()])
            ->setCreditNoteExternalId($invoiceExternalId . '-refund')
            ->setBillingAddress($this->addressModelFactory->createFromOrderAddress($billingAddress, $context))
            ->setAmount($this->amountModelFactory->createAmountForOrder($orderEntity, $context))
            ->setInvoicedAt(new DateTime());

        $data->setLineItems($this->lineItemsFactory->createFromOrder($orderEntity, $data->getAmount()->getCurrency()));

        try {
            $this->createCreditNoteRequest->execute($data); // we do not process the response.
            sleep(1);                                       // we have to wait one second, so the invoice has been published into the internal systems of tilta.

            $this->updateTransactionStatus($context, $orderEntity);

            return true;
        } catch (TiltaException $tiltaException) {
            $this->logger->critical(
                'Exception during create credit-note. (Exception: ' . $tiltaException->getMessage() . ')',
                [
                    'error' => $tiltaException->getTiltaCode(),
                    'order' => $orderEntity->getId(),
                    'order_external_id' => $orderEntity->getOrderNumber(),
                    'credit_note_external_id' => $data->getCreditNoteExternalId(),
                ]
            );

            return false;
        }
    }

    public function cancelOrder(OrderEntity $orderEntity, Context $context): bool
    {
        /** @var TiltaOrderDataEntity|null $tiltaData */
        $tiltaData = $orderEntity->getExtension(OrderDataEntityExtension::EXTENSION_NAME);
        if (!$tiltaData instanceof TiltaOrderDataEntity) {
            throw new OrderIsNotATiltaOrder();
        }

        $requestModel = new CancelOrderRequestModel($tiltaData->getOrderExternalId());

        try {
            $this->cancelOrderRequest->execute($requestModel);
            sleep(1); // we have to wait one second, so the invoice has been published into the internal systems of tilta.

            $this->updateTransactionStatus($context, $orderEntity);

            return true;
        } catch (TiltaException $tiltaException) {
            $this->logger->critical(
                'Exception during cancel order. (Exception: ' . $tiltaException->getMessage() . ')',
                [
                    'error' => $tiltaException->getTiltaCode(),
                    'order' => $orderEntity->getId(),
                    'order_external_id' => $requestModel->getOrderExternalId(),
                ]
            );

            return false;
        }
    }

    private function updateTransactionStatus(Context $context, OrderEntity $orderEntity, array $additionalData = []): void
    {
        try {
            if ($orderEntity->getOrderNumber() === null) {
                throw new RuntimeException(sprintf('Order with ID %s does not have a order-number', $orderEntity->getId()));
            }

            /** @var TiltaOrderDataEntity|null $tiltaData */
            $tiltaData = $orderEntity->getExtension(OrderDataEntityExtension::EXTENSION_NAME);
            if (!$tiltaData instanceof TiltaOrderDataEntity) {
                throw new OrderIsNotATiltaOrder();
            }

            $tiltaOrder = $this->orderDetailsRequest->execute((new GetOrderDetailsRequestModel($orderEntity->getOrderNumber())));
            $this->tiltaOrderDataRepository->update([
                array_merge([
                    TiltaOrderDataEntity::FIELD_ID => $tiltaData->getId(),
                    'version_id' => $tiltaData->getVersionId(),
                    TiltaOrderDataEntity::FIELD_ORDER_ID => $orderEntity->getId(),
                    TiltaOrderDataEntity::FIELD_STATUS => $tiltaOrder->getStatus(),

                ], $additionalData),
            ], $context);
        } catch (Exception $exception) {
            $this->logger->critical(
                'Order state can not be updated. (Exception: ' . $exception->getMessage() . ')',
                [
                    'code' => $exception->getCode(),
                    'order' => $orderEntity->getId(),
                    'order_external_id' => $orderEntity->getOrderNumber(),
                ]
            );
        }
    }
}
