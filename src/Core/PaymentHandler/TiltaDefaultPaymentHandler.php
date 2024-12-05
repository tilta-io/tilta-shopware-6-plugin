<?php

/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\PaymentHandler;

use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Service\Request\Order\CreateOrderRequest;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\CreateOrderRequestModelFactory;
use Tilta\TiltaPaymentSW6\Core\Event\TiltaPaymentFailedEvent;
use Tilta\TiltaPaymentSW6\Core\Event\TiltaPaymentSuccessfulEvent;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderDataEntity;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderDataEntity as TransactionExtension;

class TiltaDefaultPaymentHandler implements SynchronousPaymentHandlerInterface, TiltaPaymentMethod
{
    /**
     * @param EntityRepository<EntityCollection<TiltaOrderDataEntity>> $tiltaOrderDataRepository
     */
    public function __construct(
        private readonly CreateOrderRequest $createOrderRequest,
        private readonly CreateOrderRequestModelFactory $requestModelFactory,
        private readonly EntityRepository $tiltaOrderDataRepository,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $dataValidator
    ) {
    }

    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $orderEntity = $transaction->getOrder();

        $dataValidationDefinition = new DataValidationDefinition();
        $dataValidationDefinition->addSub(
            'tilta',
            (new DataValidationDefinition())
                ->add('payment_method', new NotBlank(), new Type('string'))
                ->add('payment_term', new NotBlank(), new Type('string'))
                ->add('buyer_external_id', new NotBlank(), new Type('string'))
        );
        try {
            $this->dataValidator->validate($dataBag->all(), $dataValidationDefinition);
        } catch (ConstraintViolationException $constraintViolationException) {
            throw $this->syncProcessInterrupted($transaction->getOrderTransaction()->getId(), 'Missing required Tilta data in request data.', $constraintViolationException);
        }

        $tiltaDataArray = $dataBag->all()['tilta'] ?? [];
        $tiltaRequestData = new DataBag(is_array($tiltaDataArray) ? $tiltaDataArray : []);
        $tiltaPaymentMethod = $tiltaRequestData->getAlnum('payment_method');
        /** @var string $tiltaPaymentTerm */ // has been validated by Validator
        $tiltaPaymentTerm = $tiltaRequestData->get('payment_term'); // do not use `getAlnum` because the value contains underscores
        /** @var string $buyerExternalId */ // has been validated by Validator
        $buyerExternalId = $tiltaRequestData->get('buyer_external_id'); // do not use `getAlnum` because the value could be more than alphanumerics

        try {
            $requestModel = $this->requestModelFactory->createModel($orderEntity, $tiltaPaymentMethod, $tiltaPaymentTerm, $buyerExternalId, $salesChannelContext->getContext());
            $responseModel = $this->createOrderRequest->execute($requestModel);
        } catch (TiltaException $tiltaException) {
            $this->eventDispatcher->dispatch(new TiltaPaymentFailedEvent($tiltaException, $orderEntity, $transaction->getOrderTransaction(), $requestModel ?? null));
            throw $this->syncProcessInterrupted($transaction->getOrderTransaction()->getId(), $tiltaException->getMessage(), $tiltaException);
        }

        try {
            $this->tiltaOrderDataRepository->upsert([
                [
                    TransactionExtension::FIELD_ID => Uuid::randomHex(),
                    TransactionExtension::FIELD_ORDER_ID => $orderEntity->getId(),
                    TransactionExtension::FIELD_ORDER_VERSION_ID => $orderEntity->getVersionId(),
                    TransactionExtension::FIELD_ORDER_EXTERNAL_ID => $responseModel->getOrderExternalId(),
                    TransactionExtension::FIELD_MERCHANT_EXTERNAL_ID => $responseModel->getMerchantExternalId(),
                    TransactionExtension::FIELD_BUYER_EXTERNAL_ID => $responseModel->getBuyerExternalId(),
                    TransactionExtension::FIELD_STATUS => $responseModel->getStatus(),
                ],
            ], $salesChannelContext->getContext());
        } catch (Exception $exception) {
            // do not stop the process, cause the payment has been already placed.
            $this->logger->error('Tilta payment: Saving additional order transaction data failed, but placing the order got not interrupted. ' . $exception->getMessage());
        }

        $this->eventDispatcher->dispatch(new TiltaPaymentSuccessfulEvent($orderEntity, $transaction->getOrderTransaction(), $responseModel, $salesChannelContext));
    }

    private function syncProcessInterrupted(string $orderTransactionId, string $errorMessage, ?Throwable $e = null): Throwable
    {
        if (class_exists(PaymentException::class)) {
            return PaymentException::syncProcessInterrupted($orderTransactionId, $errorMessage, $e);
        } elseif (class_exists(SyncPaymentProcessException::class)) {
            // required for shopware version <= 6.5.3
            return new SyncPaymentProcessException($orderTransactionId, $errorMessage, $e); // @phpstan-ignore-line
        }

        // should never occur - just to be safe
        return new RuntimeException('payment interrupted: ' . $errorMessage, 0, $e);
    }
}
