<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Tests\Functional\Core\Routes;

use DateTime;
use Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\Sdk\Exception\GatewayException\Facility\FacilityExceededException;
use Tilta\Sdk\Exception\GatewayException\Facility\NoActiveFacilityFoundException;
use Tilta\Sdk\Exception\GatewayException\NotFoundException\BuyerNotFoundException;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Response\Facility;
use Tilta\Sdk\Model\Response\PaymentTerm\GetPaymentTermsResponseModel;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaDefaultPaymentHandler;
use Tilta\TiltaPaymentSW6\Core\Routes\BuyerRequestFormDataRoute;
use Tilta\TiltaPaymentSW6\Core\Routes\TiltaCheckoutDataRoute;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Service\PaymentTermsService;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class TiltaCheckoutDataRouteTest extends TestCase
{
    use TiltaTestBehavior;

    private TiltaCheckoutDataRoute $route;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Service\PaymentTermsService|(\Tilta\TiltaPaymentSW6\Core\Service\PaymentTermsService&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Service\PaymentTermsService&\PHPUnit\Framework\MockObject\MockObject)
     */
    private PaymentTermsService $paymentTermsService;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper|(\Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper&\PHPUnit\Framework\MockObject\MockObject)
     */
    private CustomerAddressHelper $customerAddressHelperMock;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new TiltaCheckoutDataRoute(
            $this->paymentTermsService = $this->createMock(PaymentTermsService::class),
            $this->createMock(BuyerRequestFormDataRoute::class),
            $this->getContainer()->get(CustomerAddressHelper::class),
            $this->facilityServiceMock = $this->createMock(FacilityService::class),
            $this->getContainer()->get(CartService::class),
            $this->getContainer()->get('logger'),
        );

        $this->salesChannelContext = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();

        static::assertNotNull($this->salesChannelContext->getCustomer());

        $billingAddress = $this->salesChannelContext->getCustomer()->getActiveBillingAddress();
        $billingAddress->setCompany('test-company');

        $billingAddress->addExtension(
            CustomerAddressEntityExtension::TILTA_DATA,
            (new TiltaCustomerAddressDataEntity())->assign([
                TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => 'buyer-external-id',
                TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'DE_GMBH',
                TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new DateTime(),
            ])
        );

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $addressRepo */
        $addressRepo = $this->getContainer()->get('customer_address.repository');
        $addressRepo->upsert([
            [
                'id' => $this->salesChannelContext->getCustomer()->getDefaultBillingAddressId(),
                'company' => $billingAddress->getCompany(),
                'tiltaData' => $billingAddress->getExtension(CustomerAddressEntityExtension::TILTA_DATA)->getVars(),
            ],
        ], Context::createDefaultContext());
    }

    /**
     * tests if the data, which got provided for the struct is as expected.
     * @dataProvider successfulDataProvider
     */
    public function testSuccessful(string $methodToCall, string $paymentTermsMethod)
    {
        $method = $this->paymentTermsService->method($paymentTermsMethod);
        $method->willReturn($responseModel = new GetPaymentTermsResponseModel([
            'facility' => [
                'status' => 'new',
                'expires_at' => (new DateTime())->modify('+1 day')->getTimestamp(),
                'currency' => 'EUR',
                'total_amount' => 100000,
                'available_amount' => 100000,
                'used_amount' => 0,
            ],
            'payment_terms' => [
                [
                    'payment_method' => 'BNPL30',
                    'name' => 'invoice',
                    'due_date' => (new DateTime())->modify('+1 day')->getTimestamp(),
                    'amount' => [
                        'net' => 100,
                        'gross' => 119,
                        'tax' => 19,
                        'currency' => 'EUR',
                        'fee' => 0,
                        'fee_percentage' => 0,
                    ],
                ],
            ],
        ]));

        $expectedPaymentTermsResponse = $responseModel->getPaymentTerms()[0]->toArray();
        $expectedPaymentTermsResponse['days'] = 1;

        $response = $this->{$methodToCall}();
        static::assertInstanceOf(ArrayStruct::class, $response);
        static::assertEquals(null, $response->get('error'));
        static::assertEquals(null, $response->get('action'));
        static::assertEquals([$expectedPaymentTermsResponse], $response->get('allowedPaymentMethods'));
        static::assertEquals('buyer-external-id', $response->get('buyerExternalId'));
    }

    public static function successfulDataProvider(): array
    {
        return [
            ['executePrepareAndGetCheckoutDataForCart', 'getPaymentTermsForCart'],
            ['executePrepareAndGetCheckoutDataForOrder', 'getPaymentTermsForOrder'],
        ];
    }

    /**
     * @dataProvider errorStatesDataProvider
     */
    public function testErrorStatesCart($returnValue, ?string $expectedError = null, ?string $expectedAction = null): void
    {
        $this->_testErrorStates(
            [$this, 'executePrepareAndGetCheckoutDataForCart'],
            'getPaymentTermsForCart',
            $returnValue,
            $expectedError,
            $expectedAction
        );
    }

    /**
     * @dataProvider errorStatesDataProvider
     */
    public function testErrorStatesOrder($returnValue, ?string $expectedError = null, ?string $expectedAction = null): void
    {
        $this->_testErrorStates(
            [$this, 'executePrepareAndGetCheckoutDataForOrder'],
            'getPaymentTermsForOrder',
            $returnValue,
            $expectedError,
            $expectedAction
        );
    }

    public function errorStatesDataProvider(): array
    {
        return [
            [TiltaException::class, 'unknown-error', null],
            [BuyerNotFoundException::class, 'unknown-error', null],
            [FacilityExceededException::class, 'facility-exceeded', null],
            [NoActiveFacilityFoundException::class, null, 'facility-request-required'],
        ];
    }

    public function facilityToLowDataProvider(): array
    {
        return [
            ['executePrepareAndGetCheckoutDataForCart', 'getPaymentTermsForCart'],
            ['executePrepareAndGetCheckoutDataForOrder', 'getPaymentTermsForOrder'],
        ];
    }

    /**
     * tests if the response in the struct is correct for facility-to-low for order error
     * @dataProvider facilityToLowDataProvider
     */
    public function testFacilityToLow(string $methodToCall, string $paymentTermsMethod)
    {
        $this->paymentTermsService->method($paymentTermsMethod)->willThrowException($this->createMock(FacilityExceededException::class));

        $this->facilityServiceMock->method('getFacility')->willReturn(new Facility([
            'status' => 'new',
            'expires_at' => (new DateTime())->modify('+1 day')->getTimestamp(),
            'currency' => 'EUR',
            'total_amount' => 1,
            'available_amount' => 1,
            'used_amount' => 0,
        ]));

        $response = $this->{$methodToCall}();
        static::assertInstanceOf(ArrayStruct::class, $response);
        static::assertEquals('facility-too-low', $response->get('error'));
        static::assertEquals(null, $response->get('action'));
        static::assertEquals(null, $response->get('allowedPaymentMethods'));
        static::assertEquals(null, $response->get('buyerExternalId'));
    }

    /**
     * tests if fail behaviour produces the correct responses in the struct.
     */
    private function _testErrorStates(callable $methodToCall, string $paymentTermsMethod, $returnValue, ?string $expectedError = null, ?string $expectedAction = null)
    {
        $method = $this->paymentTermsService->method($paymentTermsMethod);
        if (is_subclass_of($returnValue, Exception::class)) {
            $method->willThrowException(is_string($returnValue) ? $this->createMock($returnValue) : $returnValue);
        } else {
            $method->willReturn($returnValue);
        }

        $this->facilityServiceMock->method('getFacility')->willReturn(null);

        $response = $methodToCall();
        static::assertInstanceOf(ArrayStruct::class, $response);
        static::assertEquals($expectedError, $response->get('error'));
        static::assertEquals($expectedAction, $response->get('action'));
        static::assertEquals(null, $response->get('allowedPaymentMethods'));
        static::assertEquals(null, $response->get('buyerExternalId'));

        if ($response->get('action') === 'buyer-registration-required' || $response->get('action') === 'facility-request-required') {
            static::assertTrue($response->has('addressFormData')); // contents got validated in separate unit-test
        }
    }

    private function executePrepareAndGetCheckoutDataForCart(): ?ArrayStruct
    {
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $paymentMethodCriteria = new Criteria();
        $paymentMethodCriteria->addFilter(new EqualsFilter('handlerIdentifier', TiltaDefaultPaymentHandler::class));
        $paymentMethodCriteria->setLimit(1);
        $tiltaPaymentMethod = $paymentMethodRepository->search($paymentMethodCriteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(PaymentMethodEntity::class, $tiltaPaymentMethod);

        $this->salesChannelContext->assign([
            SalesChannelContextService::PAYMENT_METHOD_ID => $tiltaPaymentMethod->getId(),
            'paymentMethod' => $tiltaPaymentMethod,
        ]);

        $this->addProductToCart($this->getRandomProduct($this->salesChannelContext)->getId(), $this->salesChannelContext);
        $this->addProductToCart($this->getRandomProduct($this->salesChannelContext)->getId(), $this->salesChannelContext);

        return $this->route->getCheckoutDataForSalesChannelContext($this->salesChannelContext, new RequestDataBag());
    }

    private function executePrepareAndGetCheckoutDataForOrder(): ?ArrayStruct
    {
        $orderId = $this->placeRandomOrder($this->salesChannelContext);

        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociations(['addresses', 'orderCustomer', 'billingAddress']);
        $orderEntity = $orderRepository->search($orderCriteria, $this->salesChannelContext->getContext())->first();
        static::assertInstanceOf(OrderEntity::class, $orderEntity);

        return $this->route->getCheckoutDataForOrderEntity($this->salesChannelContext, $orderEntity, new RequestDataBag());
    }
}
