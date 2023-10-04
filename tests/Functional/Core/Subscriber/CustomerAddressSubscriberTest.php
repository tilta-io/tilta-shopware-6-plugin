<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Subscriber;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class CustomerAddressSubscriberTest extends TestCase
{
    use TiltaTestBehavior;

    private CustomerEntity $customer;

    /**
     * @var (\object&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject|\Tilta\TiltaPaymentSW6\Core\Service\BuyerService|(\Tilta\TiltaPaymentSW6\Core\Service\BuyerService&\object&\PHPUnit\Framework\MockObject\MockObject)|(\Tilta\TiltaPaymentSW6\Core\Service\BuyerService&\PHPUnit\Framework\MockObject\MockObject)
     */
    private $buyerService;

    private CustomerAddressSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->customer = $this->createCustomer();
        /** @var EntityRepository $addressRepository */
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->update([[
            'id' => $this->customer->getDefaultBillingAddressId(),
            'tiltaData' => [
                TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $this->customer->getDefaultBillingAddressId(),
                TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => 'test-company',
                TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'GMBH',
                TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new \DateTime(),
            ],
        ]], Context::createDefaultContext());

        $this->subscriber = new CustomerAddressSubscriber(
            $addressRepository,
            $this->buyerService = $this->createMock(BuyerService::class),
            $this->createMock(LoggerInterface::class),
            ['__additionalAddressField1', '__additionalAddressField2', '__additionalAddressField3', '__additionalAddressField4'],
            ['__additionalCustomerField1', '__additionalCustomerField2', '__additionalCustomerField3', '__additionalCustomerField4']
        );
    }

    /**
     * @dataProvider customerChangedFieldDataProvider
     */
    public function testIfAddressGetUpdatedForCustomerUpdate(string $changedFieldName)
    {
        $this->expectBuyerGetUpdated();

        $this->subscriber->onWrittenCustomer(
            $this->generateEvent(
                CustomerDefinition::ENTITY_NAME,
                $this->customer->getId(),
                $changedFieldName
            )
        );
    }

    /**
     * @dataProvider customerAddressChangedFieldDataProvider
     */
    public function testIfAddressGetUpdatedForCustomerAddressUpdate(string $changedFieldName)
    {
        $this->expectBuyerGetUpdated();

        $this->subscriber->onWrittenCustomerAddress(
            $this->generateEvent(
                CustomerAddressDefinition::ENTITY_NAME,
                $this->customer->getDefaultBillingAddressId(),
                $changedFieldName
            )
        );
    }

    /**
     * @dataProvider customerInvalidChangedFieldDataProvider
     */
    public function testIfAddressGetNotUpdatedForCustomerUpdate(string $changedFieldName)
    {
        $this->expectBuyerNotGetUpdated();

        $this->subscriber->onWrittenCustomer(
            $this->generateEvent(
                CustomerDefinition::ENTITY_NAME,
                $this->customer->getId(),
                $changedFieldName
            )
        );
    }

    /**
     * @dataProvider customerAddressInvalidChangedFieldDataProvider
     */
    public function testIfAddressGetNotUpdatedForCustomerAddressUpdate(string $changedFieldName)
    {
        $this->expectBuyerNotGetUpdated();

        $this->subscriber->onWrittenCustomerAddress(
            $this->generateEvent(
                CustomerAddressDefinition::ENTITY_NAME,
                $this->customer->getDefaultBillingAddressId(),
                $changedFieldName
            )
        );
    }

    public function customerChangedFieldDataProvider(): array
    {
        return [
            ['email'],
            ['birthday'],
            ['__additionalCustomerField1'],
            ['__additionalCustomerField2'],
            ['__additionalCustomerField3'],
            ['__additionalCustomerField4'],
        ];
    }

    public function customerAddressChangedFieldDataProvider(): array
    {
        return [
            ['countryId'],
            ['salutationId'],
            ['firstName'],
            ['lastName'],
            ['zipcode'],
            ['company'],
            ['street'],
            ['phoneNumber'],
            ['additionalAddressLine1'],
            ['additionalAddressLine2'],
            ['__additionalAddressField1'],
            ['__additionalAddressField2'],
            ['__additionalAddressField3'],
            ['__additionalAddressField4'],
        ];
    }

    public function customerInvalidChangedFieldDataProvider(): array
    {
        return [
            ['firstName'],
            ['lastName'],
            ['_any_other_field'],
        ];
    }

    public function customerAddressInvalidChangedFieldDataProvider(): array
    {
        return [
            ['countryState'],
            ['department'],
            ['_any_other_field'],
        ];
    }

    private function expectBuyerGetUpdated()
    {
        $billingAddressId = $this->customer->getDefaultBillingAddressId();
        $this->buyerService->expects($this->once())->method('updateBuyer')->willReturnCallback(static function ($object) use ($billingAddressId) {
            self::assertInstanceOf(CustomerAddressEntity::class, $object);
            self::assertSame($billingAddressId, $object->getId());

            return true;
        });
    }

    private function expectBuyerNotGetUpdated()
    {
        $this->buyerService->expects($this->never())->method('updateBuyer');
    }

    private function generateEvent(string $entityName, string $entityId, string $changedFieldName): EntityWrittenEvent
    {
        $result = new EntityWriteResult($entityId, [
            'id' => $entityId,
            $changedFieldName => 'value',
        ], $entityName, EntityWriteResult::OPERATION_UPDATE);

        return new EntityWrittenEvent($entityName, [$result], Context::createDefaultContext());
    }
}
