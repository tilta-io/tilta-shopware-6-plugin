<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Tests\Functional\Core\PaymentHandler;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Tilta\Sdk\Enum\PaymentMethodEnum;
use Tilta\Sdk\Enum\PaymentTermEnum;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Order;
use Tilta\Sdk\Model\Request\Order\CreateOrderRequestModel;
use Tilta\Sdk\Service\Request\Order\CreateOrderRequest;
use Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\CreateOrderRequestModelFactory;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaDefaultPaymentHandler;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class TiltaDefaultPaymentHandlerTest extends TestCase
{
    use TiltaTestBehavior;

    /**
     * @dataProvider tiltaDataMissingDataProvider
     */
    public function testTiltaDataMissing(array $returnData, string $missingField = null)
    {
        $handler = new TiltaDefaultPaymentHandler(
            $createOrderRequest = $this->createMock(CreateOrderRequest::class),
            $requestModelFactory = $this->createMock(CreateOrderRequestModelFactory::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get(LoggerInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(DataValidator::class)
        );

        $orderEntity = $this->createRandomOrder();
        $transactionEntity = new OrderTransactionEntity();
        $transactionEntity->setId(Uuid::randomHex());
        $transactionEntity->setOrder($orderEntity);
        $transactionStruct = new SyncPaymentTransactionStruct($transactionEntity, $orderEntity);

        // the request should never be executed, because main validation should fail
        $createOrderRequest->expects($this->never())->method('execute');
        // the create-model should never be executed, because main validation should fail
        $requestModelFactory->expects($this->never())->method('createModel');

        $context = $this->createSalesChannelContext();

        // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
        if (!method_exists(SyncPaymentProcessException::class, 'getParameter')) {
            $this->expectException(SyncPaymentProcessException::class);
        }

        try {
            $handler->pay($transactionStruct, new RequestDataBag($returnData), $context);
        } catch (SyncPaymentProcessException $exception) {
            $previousException = $exception->getPrevious();
            static::assertInstanceOf(ConstraintViolationException::class, $previousException);
            if ($missingField) {
                static::assertCount(1, $previousException->getViolations(), 'Expected exactly 1 violation');
                $violations = $previousException->getViolations($path = '/tilta/' . $missingField);
                static::assertCount(1, $violations, 'Expected on validation error on path ' . $path);
            } else {
                static::assertCount(3, $previousException->getViolations(), 'Expected exactly 3 violations');
            }

            // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
            if (method_exists(SyncPaymentProcessException::class, 'getParameter')) {
                static::assertNotNull($exception->getParameter('errorMessage'));
                static::assertMatchesRegularExpression('/missing/i', $exception->getParameter('errorMessage'));
                if ($missingField) {
                    static::assertMatchesRegularExpression('/missing/i', $exception->getParameter('errorMessage'));
                }
            } else {
                throw $exception;
            }
        }
    }

    public static function tiltaDataMissingDataProvider(): array
    {
        $validData = [
            'payment_method' => PaymentMethodEnum::CASH,
            'payment_term' => PaymentTermEnum::BNPL7,
            'buyer_external_id' => 'buyer external id',
        ];

        $list = [];
        foreach (array_keys($validData) as $key) {
            $data = $validData;
            unset($data[$key]);
            $list[] = [[
                'tilta' => $data,
            ], $key];

            $data = $validData;
            $data[$key] = null;
            $list[] = [[
                'tilta' => $data,
            ], $key];

            $data = $validData;
            $data[$key] = '';
            $list[] = [[
                'tilta' => $data,
            ], $key];
        }

        return [
            [[]],
            [[
                'tilta' => [],
            ]],
            ...$list,
        ];
    }

    public function testSuccessful()
    {
        $handler = new TiltaDefaultPaymentHandler(
            $createOrderRequest = $this->createMock(CreateOrderRequest::class),
            $requestModelFactory = $this->createMock(CreateOrderRequestModelFactory::class),
            $tiltaOrderTransactionRepository = $this->createMock(EntityRepository::class),
            $this->getContainer()->get(LoggerInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(DataValidator::class)
        );

        $orderEntity = $this->createRandomTiltaOrder();
        $transactionEntity = new OrderTransactionEntity();
        $transactionEntity->setId(Uuid::randomHex());
        $transactionEntity->setOrder($orderEntity);
        $transactionEntity->setAmount(new CalculatedPrice(1, $orderEntity->getPrice()->getTotalPrice(), $orderEntity->getPrice()->getCalculatedTaxes(), $orderEntity->getPrice()->getTaxRules()));
        $transactionStruct = new SyncPaymentTransactionStruct($transactionEntity, $orderEntity);

        $requestModelFactory->expects($this->once())->method('createModel')->willReturn(new CreateOrderRequestModel());
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getOrderExternalId')->willReturn('test');
        $orderMock->method('getMerchantExternalId')->willReturn('test');
        $orderMock->method('getBuyerExternalId')->willReturn('test');
        $createOrderRequest->expects($this->once())->method('execute')->willReturn($orderMock);

        // Order data should be always saved.
        $tiltaOrderTransactionRepository->expects($this->once())->method('upsert');

        $handler->pay($transactionStruct, new RequestDataBag([
            'tilta' => [
                'payment_method' => PaymentMethodEnum::CASH,
                'payment_term' => PaymentTermEnum::BNPL30,
                'buyer_external_id' => 'buyer-external-id',

            ],
        ]), $this->createSalesChannelContext());
    }

    public function testIfRequestToGatewayGotHandledCorrectly()
    {
        $handler = new TiltaDefaultPaymentHandler(
            $createOrderRequest = $this->createMock(CreateOrderRequest::class),
            $requestModelFactory = $this->createMock(CreateOrderRequestModelFactory::class),
            $tiltaOrderTransactionRepository = $this->createMock(EntityRepository::class),
            $this->getContainer()->get(LoggerInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(DataValidator::class)
        );

        $orderEntity = $this->createRandomTiltaOrder();
        $transactionEntity = new OrderTransactionEntity();
        $transactionEntity->setId(Uuid::randomHex());
        $transactionEntity->setOrder($orderEntity);
        $transactionEntity->setAmount(new CalculatedPrice(1, $orderEntity->getPrice()->getTotalPrice(), $orderEntity->getPrice()->getCalculatedTaxes(), $orderEntity->getPrice()->getTaxRules()));
        $transactionStruct = new SyncPaymentTransactionStruct($transactionEntity, $orderEntity);

        $requestModelFactory->expects($this->once())->method('createModel')->willReturn(new CreateOrderRequestModel());
        $createOrderRequest->expects($this->once())->method('execute')->willThrowException(new TiltaException('test-message'));
        $tiltaOrderTransactionRepository->expects($this->never())->method('upsert');

        // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
        if (!method_exists(SyncPaymentProcessException::class, 'getParameter')) {
            $this->expectException(SyncPaymentProcessException::class);
        }

        try {
            $handler->pay($transactionStruct, new RequestDataBag([
                'tilta' => [
                    'payment_method' => PaymentMethodEnum::CASH,
                    'payment_term' => PaymentTermEnum::BNPL30,
                    'buyer_external_id' => 'buyer-external-id',

                ],
            ]), $this->createSalesChannelContext());
        } catch (SyncPaymentProcessException $exception) {
            static::assertInstanceOf(TiltaException::class, $exception->getPrevious(), 'original exception should be in the sync-payment-exception.');
            // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
            if (method_exists(SyncPaymentProcessException::class, 'getParameter')) {
                static::assertNotNull($exception->getParameter('errorMessage'));
                static::assertMatchesRegularExpression('/test-message/i', $exception->getParameter('errorMessage'));
            } else {
                throw $exception;
            }
        }
    }

    public function testIfRequestModelBuildingExceptionGotHandledCorrectly()
    {
        $handler = new TiltaDefaultPaymentHandler(
            $createOrderRequest = $this->createMock(CreateOrderRequest::class),
            $requestModelFactory = $this->createMock(CreateOrderRequestModelFactory::class),
            $tiltaOrderTransactionRepository = $this->createMock(EntityRepository::class),
            $this->getContainer()->get(LoggerInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(DataValidator::class)
        );

        $orderEntity = $this->createRandomTiltaOrder();
        $transactionEntity = new OrderTransactionEntity();
        $transactionEntity->setId(Uuid::randomHex());
        $transactionEntity->setOrder($orderEntity);
        $transactionEntity->setAmount(new CalculatedPrice(1, $orderEntity->getPrice()->getTotalPrice(), $orderEntity->getPrice()->getCalculatedTaxes(), $orderEntity->getPrice()->getTaxRules()));
        $transactionStruct = new SyncPaymentTransactionStruct($transactionEntity, $orderEntity);

        $requestModelFactory->expects($this->once())->method('createModel')->willThrowException(new TiltaException('test-message'));
        $createOrderRequest->expects($this->never())->method('execute');
        $tiltaOrderTransactionRepository->expects($this->never())->method('upsert');

        // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
        if (!method_exists(SyncPaymentProcessException::class, 'getParameter')) {
            $this->expectException(SyncPaymentProcessException::class);
        }

        try {
            $handler->pay($transactionStruct, new RequestDataBag([
                'tilta' => [
                    'payment_method' => PaymentMethodEnum::CASH,
                    'payment_term' => PaymentTermEnum::BNPL30,
                    'buyer_external_id' => 'buyer-external-id',

                ],
            ]), $this->createSalesChannelContext());
        } catch (SyncPaymentProcessException $exception) {
            static::assertInstanceOf(TiltaException::class, $exception->getPrevious(), 'original exception should be in the sync-payment-exception.');

            // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
            if (method_exists(SyncPaymentProcessException::class, 'getParameter')) {
                static::assertNotNull($exception->getParameter('errorMessage'));
                static::assertMatchesRegularExpression('/test-message/i', $exception->getParameter('errorMessage'));
            } else {
                throw $exception;
            }
        }
    }

    public function testIfSavingTransactionDataToDatabaseWontBreakProcessInCaseOfFailure()
    {
        $handler = new TiltaDefaultPaymentHandler(
            $createOrderRequest = $this->createMock(CreateOrderRequest::class),
            $requestModelFactory = $this->createMock(CreateOrderRequestModelFactory::class),
            $tiltaOrderTransactionRepository = $this->createMock(EntityRepository::class),
            $loggerMock = $this->createMock(LoggerInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(DataValidator::class)
        );

        $orderEntity = $this->createRandomTiltaOrder();
        $transactionEntity = new OrderTransactionEntity();
        $transactionEntity->setId(Uuid::randomHex());
        $transactionEntity->setOrder($orderEntity);
        $transactionEntity->setAmount(new CalculatedPrice(1, $orderEntity->getPrice()->getTotalPrice(), $orderEntity->getPrice()->getCalculatedTaxes(), $orderEntity->getPrice()->getTaxRules()));
        $transactionStruct = new SyncPaymentTransactionStruct($transactionEntity, $orderEntity);

        $requestModelFactory->expects($this->once())->method('createModel')->willReturn(new CreateOrderRequestModel());
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getOrderExternalId')->willReturn('test');
        $orderMock->method('getMerchantExternalId')->willReturn('test');
        $orderMock->method('getBuyerExternalId')->willReturn('test');
        $createOrderRequest->expects($this->once())->method('execute')->willReturn($orderMock);

        // exception should be handled, but should not get converted into a sync-payment-exception.
        // it should be just logged.
        $tiltaOrderTransactionRepository->expects($this->once())->method('upsert')->willThrowException(new \Exception('test-exception'));
        $loggerMock->expects($this->once())->method('error');

        $handler->pay($transactionStruct, new RequestDataBag([
            'tilta' => [
                'payment_method' => PaymentMethodEnum::CASH,
                'payment_term' => PaymentTermEnum::BNPL30,
                'buyer_external_id' => 'buyer-external-id',

            ],
        ]), $this->createSalesChannelContext());
    }
}
