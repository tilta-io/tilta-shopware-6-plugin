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
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Service\Request\Order\CreateOrderRequest;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\CreateOrderRequestModelFactory;
use Tilta\TiltaPaymentSW6\Core\Event\TiltaPaymentFailedEvent;
use Tilta\TiltaPaymentSW6\Core\Event\TiltaPaymentSuccessfulEvent;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderTransactionDataEntity as TransactionExtension;

class TiltaDefaultPaymentHandler implements SynchronousPaymentHandlerInterface, TiltaPaymentMethod
{
    private CreateOrderRequest $createOrderRequest;

    private CreateOrderRequestModelFactory $requestModelFactory;

    private EntityRepository $tiltaOrderTransactionRepository;

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    private DataValidator $dataValidator;

    public function __construct(
        CreateOrderRequest $createOrderRequest,
        CreateOrderRequestModelFactory $requestModelFactory,
        EntityRepository $tiltaOrderTransactionRepository,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher,
        DataValidator $dataValidator
    ) {
        $this->createOrderRequest = $createOrderRequest;
        $this->requestModelFactory = $requestModelFactory;
        $this->tiltaOrderTransactionRepository = $tiltaOrderTransactionRepository;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->dataValidator = $dataValidator;
    }

    public function pay(SyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $orderEntity = $transaction->getOrder();

        $dataValidationDefinition = new DataValidationDefinition();
        $dataValidationDefinition->addSub(
            'tilta',
            (new DataValidationDefinition())
                ->add('payment_method', new NotBlank(), new Type('string'))
                ->add('buyer_external_id', new NotBlank(), new Type('string'))
        );
        try {
            $this->dataValidator->validate($dataBag->all(), $dataValidationDefinition);
        } catch (ConstraintViolationException $constraintViolationException) {
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'Missing required Tilta data in request data.', $constraintViolationException);
        }

        /** @var RequestDataBag $tiltaRequestData */
        $tiltaRequestData = $dataBag->get('tilta');
        $tiltaPaymentMethod = $tiltaRequestData->getAlnum('payment_method');
        $buyerExternalId = $tiltaRequestData->get('buyer_external_id'); // do not use `getAlnum` because the value could be more than alphanumerics

        if (!is_string($buyerExternalId)) {
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), 'buyer-external-id is not a string');
        }

        try {
            $requestModel = $this->requestModelFactory->createModel($orderEntity, $tiltaPaymentMethod, $buyerExternalId);
            $responseModel = $this->createOrderRequest->execute($requestModel);
        } catch (TiltaException $tiltaException) {
            $this->eventDispatcher->dispatch(new TiltaPaymentFailedEvent($tiltaException, $orderEntity, $transaction->getOrderTransaction(), $requestModel ?? null));
            throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), $tiltaException->getMessage(), $tiltaException);
        }

        try {
            $this->tiltaOrderTransactionRepository->upsert([
                [
                    TransactionExtension::FIELD_ORDER_TRANSACTION_ID => $transaction->getOrderTransaction()->getId(),
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

        $this->eventDispatcher->dispatch(new TiltaPaymentSuccessfulEvent($orderEntity, $transaction->getOrderTransaction(), $responseModel));
    }
}
