<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Tests\Functional\Core\Routes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Tilta\TiltaPaymentSW6\Core\Routes\CreateFacilityRoute;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;

class CreateFacilityRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelFunctionalTestBehaviour;

    private CreateFacilityRoute $route;

    private CustomerAddressEntity $customerAddress;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Service\BuyerService|(\Tilta\TiltaPaymentSW6\Core\Service\BuyerService&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Service\BuyerService&\PHPUnit\Framework\MockObject\MockObject)
     */
    private BuyerService $buyerServiceMock;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Service\FacilityService|(\Tilta\TiltaPaymentSW6\Core\Service\FacilityService&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Service\FacilityService&\PHPUnit\Framework\MockObject\MockObject)
     */
    private FacilityService $facilityServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new CreateFacilityRoute(
            $this->getContainer()->get(DataValidator::class),
            $this->buyerServiceMock = $this->createMock(BuyerService::class),
            $this->facilityServiceMock = $this->createMock(FacilityService::class),
            $this->getContainer()->get('customer_address.repository'),
            $this->getContainer()->get('salutation.repository'),
            $this->getContainer()->get('logger')
        );

        /** @var EntityRepository $customerRepo */
        $customerRepo = $this->getContainer()->get('customer.repository');
        $customer = $customerRepo->search((new Criteria([$this->createCustomer()])), Context::createDefaultContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $addressRepo */
        $addressRepo = $this->getContainer()->get('customer_address.repository');
        $addressRepo->upsert([
            [
                'id' => $customer->getDefaultBillingAddressId(),
                'company' => 'test company',
            ],
        ], Context::createDefaultContext());

        $customerAddress = $addressRepo->search((new Criteria([$customer->getDefaultBillingAddressId()])), Context::createDefaultContext())->first();
        static::assertInstanceOf(CustomerAddressEntity::class, $customerAddress);
        $this->customerAddress = $customerAddress;
    }

    public function testSuccessful(): void
    {
        $this->buyerServiceMock->expects($this->once())->method('updateCustomerAddressData');
        $this->facilityServiceMock->expects($this->once())->method('createFacilityForBuyerIfNotExist');

        $requestData = new RequestDataBag([
            'incorporatedAtDay' => 20,
            'incorporatedAtMonth' => 5,
            'incorporatedAtYear' => 2000,
            'salutationId' => $this->getValidSalutationId(),
            'phoneNumber' => '0123456789',
            'legalForm' => 'GMBH',
        ]);

        $response = $this->route->requestFacilityPost($requestData, $this->customerAddress);

        static::assertInstanceOf(SuccessResponse::class, $response);
    }

    /**
     * @dataProvider failureDataProvider
     */
    public function testFailure(string $field, $value): void
    {
        $this->expectException(ConstraintViolationException::class);

        $requestData = new RequestDataBag([
            'incorporatedAtDay' => 20,
            'incorporatedAtMonth' => 5,
            'incorporatedAtYear' => 2000,
            'salutationId' => $this->getValidSalutationId(),
            'phoneNumber' => '0123456789',
            'legalForm' => 'GMBH',
        ]);

        $requestData->set($field, $value);

        $response = $this->route->requestFacilityPost($requestData, $this->customerAddress);

        static::assertInstanceOf(SuccessResponse::class, $response);
    }

    public function failureDataProvider(): array
    {
        return [
            ['incorporatedAtDay', null],
            ['incorporatedAtDay', 99],
            ['incorporatedAtMonth', null],
            ['incorporatedAtMonth', 99],
            ['incorporatedAtYear', null],
            ['incorporatedAtYear', 0],
            ['salutationId', null],
            ['salutationId', Uuid::randomHex()],
            ['phoneNumber', null],
            ['legalForm', null],
            ['legalForm', 'invalid-value'],
        ];
    }
}
