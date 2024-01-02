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
use Tilta\TiltaPaymentSW6\Core\Service\LegalFormService;

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

    private CustomerEntity $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = new CreateFacilityRoute(
            $this->getContainer()->get(DataValidator::class),
            $this->buyerServiceMock = $this->createMock(BuyerService::class),
            $this->facilityServiceMock = $this->createMock(FacilityService::class),
            $this->getContainer()->get('customer_address.repository'),
            $this->getContainer()->get('salutation.repository'),
            $this->getContainer()->get('logger'),
            $legalFormService = $this->createMock(LegalFormService::class)
        );

        $legalFormService->method('getLegalFormsOnlyCodes')->willReturn(['DE_GMBH']);
        $legalFormService->method('getLegalForms')->willReturn([[
            'value' => 'DE_GMBH',
            'label' => 'GmbH',
        ]]);

        /** @var EntityRepository $customerRepo */
        $customerRepo = $this->getContainer()->get('customer.repository');
        $this->customer = $customerRepo->search((new Criteria([$this->createCustomer()])), Context::createDefaultContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $this->customer);

        /** @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository $addressRepo */
        $addressRepo = $this->getContainer()->get('customer_address.repository');
        $addressRepo->upsert([
            [
                'id' => $this->customer->getDefaultBillingAddressId(),
                'company' => 'test company',
            ],
        ], Context::createDefaultContext());

        $customerAddress = $addressRepo->search((new Criteria([$this->customer->getDefaultBillingAddressId()])), Context::createDefaultContext())->first();
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
            'phoneNumber' => '+491731010101',
            'legalForm' => 'DE_GMBH',
            'toc' => '1',
        ]);

        $response = $this->route->requestFacilityPost(Context::createDefaultContext(), $requestData, $this->customer, $this->customerAddress->getId());

        static::assertInstanceOf(SuccessResponse::class, $response);
    }

    /**
     * @dataProvider failureDataProvider
     */
    public function testFailure(string $field, $value, string $expectedError = null, string $violationField = null): void
    {
        $requestData = new RequestDataBag([
            'incorporatedAtDay' => 20,
            'incorporatedAtMonth' => 5,
            'incorporatedAtYear' => 2000,
            'salutationId' => $this->getValidSalutationId(),
            'phoneNumber' => '+491731010101',
            'legalForm' => 'DE_GMBH',
            'toc' => '1',
        ]);

        $requestData->set($field, $value);

        try {
            $this->route->requestFacilityPost(Context::createDefaultContext(), $requestData, $this->customer, $this->customerAddress->getId());
            $this->fail('ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            $violations = $constraintViolationException->getViolations();
            static::assertEquals(1, $violations->count(), 'there should by exactly one violations');
            static::assertEquals('/' . ($violationField ?? $field), $violations->get(1)->getPropertyPath());
            if ($expectedError !== null) {
                static::assertEquals($expectedError, $violations->get(1)->getCode());
            }
        }
    }

    public function failureDataProvider(): array
    {
        // we won't validate for message on the date-fields, because of different messages within different SW-Versions
        return [
            ['incorporatedAtDay', null, null, 'incorporatedAt'],
            ['incorporatedAtDay', 99, null, 'incorporatedAt'],
            ['incorporatedAtMonth', null, null, 'incorporatedAt'],
            ['incorporatedAtMonth', 99, null, 'incorporatedAt'],
            ['incorporatedAtYear', null, null, 'incorporatedAt'],
            ['incorporatedAtYear', 0, null, 'incorporatedAt'],
            ['salutationId', null, 'VIOLATION::IS_BLANK_ERROR'],
            ['salutationId', Uuid::randomHex(), 'VIOLATION::NO_SUCH_CHOICE_ERROR'],
            ['phoneNumber', null, 'VIOLATION::IS_BLANK_ERROR'],
            ['legalForm', null, 'VIOLATION::IS_BLANK_ERROR'],
            ['legalForm', 'invalid-value', 'VIOLATION::NO_SUCH_CHOICE_ERROR'],
            ['toc', null, 'VIOLATION::IS_BLANK_ERROR'],
            ['toc', 'invalid-value', 'VIOLATION::NOT_EQUAL_ERROR'],
        ];
    }

    /**
     * @dataProvider invalidPhoneNumbersProvider
     */
    public function testInvalidPhoneNumbers(string $value): void
    {
        $requestData = new RequestDataBag([
            'incorporatedAtDay' => 20,
            'incorporatedAtMonth' => 5,
            'incorporatedAtYear' => 2000,
            'salutationId' => $this->getValidSalutationId(),
            'phoneNumber' => $value,
            'legalForm' => 'DE_GMBH',
            'toc' => '1',
        ]);

        try {
            $this->route->requestFacilityPost(Context::createDefaultContext(), $requestData, $this->customer, $this->customerAddress->getId());
            $this->fail('ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $constraintViolationException) {
            $violations = $constraintViolationException->getViolations();
            static::assertEquals(1, $violations->count(), 'there should by exactly one violations');
            static::assertEquals('/phoneNumber', $violations->get(1)->getPropertyPath());
            static::assertEquals('VIOLATION::REGEX_FAILED_ERROR', $violations->get(1)->getCode());
        }
    }

    public function invalidPhoneNumbersProvider(): array
    {
        return [
            ['01731010101'],
            ['491731010101'],
            ['+49 1731010101'],
            ['+49 173 1010101'],
            ['+49 173 101 010 1'],
        ];
    }
}
