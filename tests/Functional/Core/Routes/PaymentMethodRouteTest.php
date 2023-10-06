<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Tests\Functional\Core\Routes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaDefaultPaymentHandler;
use Tilta\TiltaPaymentSW6\Core\Routes\PaymentMethodRoute;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class PaymentMethodRouteTest extends TestCase
{
    use TiltaTestBehavior;

    private const TILTA_PAYMENT_METHOD = '26543203fab44b3aa9b9f22a8fb26522';

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Service\ConfigService|(\Tilta\TiltaPaymentSW6\Core\Service\ConfigService&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Service\ConfigService&\PHPUnit\Framework\MockObject\MockObject)
     */
    private ConfigService $configService;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Service\FacilityService|(\Tilta\TiltaPaymentSW6\Core\Service\FacilityService&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Service\FacilityService&\PHPUnit\Framework\MockObject\MockObject)
     */
    private FacilityService $facilityService;

    private PaymentMethodRoute $route;

    private RequestStack $requestStack;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute|(\Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute&\PHPUnit\Framework\MockObject\MockObject)
     */
    private AbstractPaymentMethodRoute $decoratedRoute;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute|(\Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute&\PHPUnit\Framework\MockObject\MockObject)
     */
    private ContextSwitchRoute $contextSwitchRoute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new PaymentMethodRoute(
            $this->decoratedRoute = $this->createMock(\Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute::class),
            $this->configService = $this->createMock(ConfigService::class),
            $this->requestStack = new RequestStack(),
            $this->getContainer()->get('order.repository'),
            $this->getContainer()->get(CustomerAddressHelper::class),
            $this->getContainer()->get(CartService::class),
            $this->facilityService = $this->createMock(FacilityService::class),
            $this->contextSwitchRoute = $this->createMock(ContextSwitchRoute::class)
        );
    }

    public function testMethodIsAvailable()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->never())->method('switchContext');

        $salesChannelContext = $this->getSalesChannelContext();
        $this->addValidTiltaData($salesChannelContext);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(1, $collection->count(), 'All tilta payment methods should be returned.');
        static::assertEquals(3, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testMethodNotAvailableBecauseOfMissingConfig()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(false);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->once())->method('switchContext')->willReturnCallback(function (RequestDataBag $data, SalesChannelContext $context) {
            static::assertNotEquals(self::TILTA_PAYMENT_METHOD, $data->get(SalesChannelContextService::PAYMENT_METHOD_ID), 'switch context should not switch to tilta payment method.');

            return $this->createMock(ContextTokenResponse::class);
        });

        $salesChannelContext = $this->getSalesChannelContext();
        $this->addValidTiltaData($salesChannelContext);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(0, $collection->count(), 'Tilta payment method should be filtered out, because config is not ready.');
        static::assertEquals(2, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testMethodNotAvailableBecauseOfAmountToLow()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(false);
        $this->contextSwitchRoute->expects($this->once())->method('switchContext')->willReturnCallback(function (RequestDataBag $data, SalesChannelContext $context) {
            static::assertNotEquals(self::TILTA_PAYMENT_METHOD, $data->get(SalesChannelContextService::PAYMENT_METHOD_ID), 'switch context should not switch to tilta payment method.');

            return $this->createMock(ContextTokenResponse::class);
        });

        $salesChannelContext = $this->getSalesChannelContext();
        $this->addValidTiltaData($salesChannelContext);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(0, $collection->count(), 'Tilta payment method should be filtered out, because totalAmount of cart is too low.');
        static::assertEquals(2, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testMethodNotAvailableBecauseCustomerIsNotACompany()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->once())->method('switchContext')->willReturnCallback(function (RequestDataBag $data, SalesChannelContext $context) {
            static::assertNotEquals(self::TILTA_PAYMENT_METHOD, $data->get(SalesChannelContextService::PAYMENT_METHOD_ID), 'switch context should not switch to tilta payment method.');

            return $this->createMock(ContextTokenResponse::class);
        });

        $salesChannelContext = $this->getSalesChannelContext();
        $this->addValidTiltaData($salesChannelContext);
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->assign([
            'company' => null,
        ]);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(0, $collection->count(), 'Tilta payment method should be filtered out, because customer is not a company.');
        static::assertEquals(2, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testMethodNotAvailableBecauseCustomerHasEmptyCompany()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->once())->method('switchContext')->willReturnCallback(function (RequestDataBag $data, SalesChannelContext $context) {
            static::assertNotEquals(self::TILTA_PAYMENT_METHOD, $data->get(SalesChannelContextService::PAYMENT_METHOD_ID), 'switch context should not switch to tilta payment method.');

            return $this->createMock(ContextTokenResponse::class);
        });

        $salesChannelContext = $this->getSalesChannelContext();
        $this->addValidTiltaData($salesChannelContext);
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->assign([
            'company' => '',
        ]);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(0, $collection->count(), 'Tilta payment method should be filtered out, because customer is not a company.');
        static::assertEquals(2, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testMethodIsAvailableForCustomerWithoutTiltaData()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->never())->method('switchContext');

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->assign([
            'company' => 'make sure company is set',
        ]);
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->setExtensions([
            CustomerAddressEntityExtension::TILTA_DATA => null,
        ]);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(1, $collection->count(), 'Tilta payment method should be available, because customer can create a buyer/facility within the checkout');
        static::assertEquals(3, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testMethodIsAvailableForCustomerWithEmptyTiltaData()
    {
        $this->requestStack->push(new Request());
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->never())->method('switchContext');

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->assign([
            'company' => 'make sure company is set',
        ]);
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->setExtensions([
            CustomerAddressEntityExtension::TILTA_DATA => new TiltaCustomerAddressDataEntity(),
        ]);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(1, $collection->count(), 'Tilta payment method should be available, because customer can create a buyer/facility within the checkout');
        static::assertEquals(3, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testIfPaymentMethodIsAvailableForOrder()
    {
        $context = $this->createSalesChannelContextWithTiltaBuyer();
        $orderId = $this->createRandomOrder($context)->getId();

        $this->requestStack->push(new Request([
            'orderId' => $orderId,
        ]));
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->never())->method('switchContext');

        $salesChannelContext = $this->getSalesChannelContext($context);
        $this->addValidTiltaData($salesChannelContext);

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(1, $collection->count(), 'Tilta payment method should be available, because customer can create a buyer/facility within the checkout');
        static::assertEquals(3, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    public function testIfPaymentMethodIsNotAvailableForOrderBecauseOfNotMappableAddress()
    {
        $context = $this->createSalesChannelContextWithTiltaBuyer();
        $orderId = $this->createRandomOrder($context)->getId();

        $this->requestStack->push(new Request([
            'orderId' => $orderId,
        ]));
        $this->configService->method('isConfigReady')->willReturn(true);
        $this->facilityService->method('checkCartAmount')->willReturn(true);
        $this->contextSwitchRoute->expects($this->once())->method('switchContext')->willReturnCallback(function (RequestDataBag $data, SalesChannelContext $context) {
            static::assertNotEquals(self::TILTA_PAYMENT_METHOD, $data->get(SalesChannelContextService::PAYMENT_METHOD_ID), 'switch context should not switch to tilta payment method.');

            return $this->createMock(ContextTokenResponse::class);
        });

        $salesChannelContext = $this->getSalesChannelContext($context);
        $this->addValidTiltaData($salesChannelContext);

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('customer_address.repository');
        $repo->update([[
            'id' => $context->getCustomer()->getDefaultBillingAddressId(),
            'firstName' => 'Another Person',
        ]], Context::createDefaultContext());

        $this->initDefaultResponse(true);

        $response = $this->route->load(new Request([
            'onlyAvailable' => true,
        ]), $salesChannelContext, new Criteria());
        $collection = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods());
        static::assertEquals(0, $collection->count(), 'Tilta payment method should be not available, because no buyer does exist for not mappable address.');
        static::assertEquals(2, $response->getPaymentMethods()->count(), 'other payment methods should be not affected.');
    }

    private function initDefaultResponse($addTiltaPaymentMethod = true): void
    {
        $collection = new PaymentMethodCollection();

        if ($addTiltaPaymentMethod) {
            // add it to the first entry to make sure, that switch-context-route COULD switch to it.
            $entity1 = new PaymentMethodEntity();
            $entity1->setId(self::TILTA_PAYMENT_METHOD);
            $entity1->setHandlerIdentifier(TiltaDefaultPaymentHandler::class);
            $collection->add($entity1);
        }

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('payment_method.repository');

        $defaultPaymentMethod = $repo->search((new Criteria())->setLimit(1)->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('handlerIdentifier', TiltaDefaultPaymentHandler::class)])), Context::createDefaultContext())->first();
        static::assertNotNull($defaultPaymentMethod, 'no default payment method was found for testing');
        $collection->add($defaultPaymentMethod);

        $entity3 = new PaymentMethodEntity();
        $entity3->setId(Uuid::randomHex());
        $entity3->setHandlerIdentifier('something-else2');
        $collection->add($entity3);

        $returnResponse = $this->createMock(PaymentMethodRouteResponse::class);
        $returnResponse->method('getPaymentMethods')->willReturn($collection);

        $this->decoratedRoute->method('load')->willReturn($returnResponse);

        // test if mocking was successful
        $response = $this->decoratedRoute->load(new Request(), $this->createMock(SalesChannelContext::class), new Criteria());
        $count = $this->extractTiltaPaymentMethodsFromList($response->getPaymentMethods())->count();
        static::assertEquals($addTiltaPaymentMethod ? 1 : 0, $count, sprintf('Mocked default route should return %s tilta payment method(s)', $addTiltaPaymentMethod ? 1 : 0));
    }

    private function extractTiltaPaymentMethodsFromList(EntityCollection $collection): EntityCollection
    {
        return $collection->filterByProperty('handlerIdentifier', TiltaDefaultPaymentHandler::class);
    }

    private function getSalesChannelContext(SalesChannelContext $context = null): SalesChannelContext
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(self::TILTA_PAYMENT_METHOD);
        $paymentMethod->setActive(true);
        $paymentMethod->setHandlerIdentifier(TiltaDefaultPaymentHandler::class);

        $context = $context ?: $this->createSalesChannelContextWithTiltaBuyer();
        $context->assign([SalesChannelContextService::PAYMENT_METHOD_ID, $paymentMethod->getId()]);
        $context->assign([
            'paymentMethod' => $paymentMethod,
        ]);

        return $context;
    }

    private function addValidTiltaData(SalesChannelContext $salesChannelContext)
    {
        $tiltaData = new TiltaCustomerAddressDataEntity();
        $tiltaData->assign([
            TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => 'buyer-id',
            TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $salesChannelContext->getCustomer()->getDefaultBillingAddressId(),
            TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new \DateTime(),
            TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'DE_GMB',
            TiltaCustomerAddressDataEntity::FIELD_TOTAL_AMOUNT => 999,
            TiltaCustomerAddressDataEntity::FIELD_VALID_UNTIL => (new \DateTime())->modify('+1 day'),
        ]);
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->assign([
            'company' => 'make sure company is set',
        ]);
        $salesChannelContext->getCustomer()->getDefaultBillingAddress()->setExtensions([
            CustomerAddressEntityExtension::TILTA_DATA => $tiltaData,
        ]);
    }
}
