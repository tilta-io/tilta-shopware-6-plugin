<?php
/*
 * Copyright (c) Tilta Fintech GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Tests\Functional\Core\Service;

use DateTime;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tilta\Sdk\Model\Request\Buyer\CreateBuyerRequestModel;
use Tilta\Sdk\Model\Request\Buyer\UpdateBuyerRequestModel;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;

class BuyerServiceTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetBuyerExternalIdWithAddressAttribute(): void
    {
        $tiltaData = new TiltaCustomerAddressDataEntity();
        $address = $this->getAddressWithId('123', 'customer-number', '456');
        $address->addExtension(CustomerAddressEntityExtension::TILTA_DATA, $tiltaData);

        $result = BuyerService::generateBuyerExternalId($address);
        static::assertEquals('customer-number-456', $result);

        $tiltaData->__set(TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID, 'buyer-external-id');

        $result = BuyerService::generateBuyerExternalId($address);
        static::assertEquals('buyer-external-id', $result);
    }

    public function testGetBuyerExternalIdWithoutAddressAttribute(): void
    {
        $address = $this->getAddressWithId('123', 'customer-number', '456');

        $result = BuyerService::generateBuyerExternalId($address);
        static::assertEquals('customer-number-456', $result);
    }

    public function testValidateAddressWithoutData(): void
    {
        $buyerService = new BuyerService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get('translator'),
            $this->createMock(ContainerInterface::class),
            $this->createMock(SystemConfigService::class),
        );

        $this->expectException(MissingBuyerInformationException::class);
        $buyerService->validateAdditionalData(new CustomerAddressEntity());
    }

    public function testValidateAddressWithData(): void
    {
        $buyerService = new BuyerService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get('translator'),
            $this->createMock(ContainerInterface::class),
            $this->createMock(SystemConfigService::class),
        );

        $buyerService->validateAdditionalData($this->getValidAddress());
        static::assertTrue(true);
    }

    /**
     * @dataProvider missingDataDataProvider
     * @param mixed $setValue
     */
    public function testValidateAddressMissingData(string $entity, string $field, $setValue): void
    {
        $buyerService = new BuyerService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get('translator'),
            $this->createMock(ContainerInterface::class),
            $this->createMock(SystemConfigService::class),
        );

        $address = $this->getValidAddress();
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);
        $tiltaData->assign([]); // does not make any sense. This makes sure that no code-style tool will remove an "unused" variable

        // set null value on entity to trigger error on validation
        ${$entity}->__set($field, $setValue);

        $this->expectException(MissingBuyerInformationException::class);
        $buyerService->validateAdditionalData($address);
    }

    public function missingDataDataProvider(): array
    {
        return [
            ['address', 'company', null],
            ['address', 'company', ''],
            ['address', 'phoneNumber', null],
            ['address', 'phoneNumber', ''],
            ['address', 'salutationId', null],
            // ['tiltaData', TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID, ''], // make no sense, because this will be set by module automatically
            // ['tiltaData', TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT, null], // can not be null
            // ['tiltaData', TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM, null], // can not be null
            ['tiltaData', TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM, ''],
        ];
    }

    public function testUpdateCustomerData(): void
    {
        $buyerService = new BuyerService(
            $customerAddressRepositoryMock = $this->createMock(EntityRepository::class),
            $tiltaDataRepositoryMock = $this->createMock(EntityRepository::class),
            $this->getContainer()->get('translator'),
            $this->createMock(ContainerInterface::class),
            $this->createMock(SystemConfigService::class),
        );
        $customerAddressRepositoryMock->expects($this->once())->method('upsert');
        $tiltaDataRepositoryMock->expects($this->once())->method('upsert');

        $address = $this->getValidAddress();

        $customerAddressRepositoryMock->method('upsert')->willReturnCallback(static function (array $data, $context) use ($address) {
            self::assertIsArray($data);
            self::assertCount(1, $data);
            self::assertIsArray($data[0] ?? null);
            self::assertEquals($address->getId(), $data[0]['id']);
            self::assertEquals('updated-12345', $data[0]['phoneNumber']);
            self::assertEquals('updated-abc', $data[0]['salutationId']);
        });

        $tiltaDataRepositoryMock->method('upsert')->willReturnCallback(static function (array $data, $context) use ($address) {
            self::assertIsArray($data);
            self::assertCount(1, $data);
            self::assertIsArray($data[0] ?? null);
            self::assertEquals($address->getId(), $data[0][TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID]);
            self::assertInstanceOf(\DateTimeInterface::class, $data[0][TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT]);
            self::assertEquals((new DateTime())->setDate(2000, 5, 15)->getTimestamp(), $data[0][TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT]->getTimestamp());
            self::assertEquals('updated-GMBH', $data[0][TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM]);
        });

        $buyerService->updateCustomerAddressData(
            $this->getValidAddress(),
            [
                'phoneNumber' => 'updated-12345',
                'salutationId' => 'updated-abc',
                'incorporatedAt' => (new DateTime())->setDate(2000, 5, 15),
                'legalForm' => 'updated-GMBH',
            ]
        );
    }

    public function testCreateCreateBuyerRequestModel(): void
    {
        $buyerService = new BuyerService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get('translator'),
            $this->createMock(ContainerInterface::class),
            $configServiceMock = $this->createMock(SystemConfigService::class),
        );
        $configServiceMock->method('get')->willReturnMap([
            'TiltaPaymentSW6.config.salutationMale', null, 'salutation-id-male',
            'TiltaPaymentSW6.config.salutationFemale', null, 'salutation-id-female',
            'TiltaPaymentSW6.config.salutationFallback', null, 'MR',
        ]);

        $requestModel = $buyerService->createCreateBuyerRequestModel($this->getValidAddress());
        static::assertInstanceOf(CreateBuyerRequestModel::class, $requestModel);
        $requestModel->validateFields();
        static::assertIsArray($requestModel->toArray());
        // no additional test, because the model got validated.
    }

    public function testCreateUpdateBuyerRequestModel(): void
    {
        $buyerService = new BuyerService(
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get('translator'),
            $this->createMock(ContainerInterface::class),
            $configServiceMock = $this->createMock(SystemConfigService::class),
        );
        $configServiceMock->method('get')->willReturnMap([
            'TiltaPaymentSW6.config.salutationMale', null, 'salutation-id-male',
            'TiltaPaymentSW6.config.salutationFemale', null, 'salutation-id-female',
            'TiltaPaymentSW6.config.salutationFallback', null, 'MR',
        ]);

        $requestModel = $buyerService->createUpdateBuyerRequestModel($this->getValidAddress());
        static::assertInstanceOf(UpdateBuyerRequestModel::class, $requestModel);
        $requestModel->validateFields();
        static::assertIsArray($requestModel->toArray());
        // no additional test, because the model got validated.
    }

    private function getAddressWithId(string $customerId, string $customerNumber, string $addressId): CustomerAddressEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($customerId);
        $customer->setCustomerNumber($customerNumber);

        $address = new CustomerAddressEntity();
        $address->setId($addressId);
        $address->setCustomer($customer);

        $customer->setDefaultBillingAddress($address);
        $customer->setDefaultShippingAddress($address);

        return $address;
    }

    private function getValidCustomer(string $id = '123', string $customerNumber = '123456'): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setCustomerNumber($customerNumber);
        $customer->setEmail('unittesting@php.local');

        return $customer;
    }

    private function getValidAddress(): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setId('987');
        $address->setCustomer($this->getValidCustomer());
        $address->setCompany('company');
        $address->setPhoneNumber('0123456789');
        $address->setSalutationId('salutation-id-male');
        $address->setFirstname('Firstname');
        $address->setLastname('Lastname');

        $country = new CountryEntity();
        $country->setIso('DE');

        $address->setCountry($country);
        $address->setCity('city');
        $address->setZipcode('12345');
        $address->setStreet('Teststreet 1341');
        $address->setAdditionalAddressLine1('additional line');
        $address->setAdditionalAddressLine1('additional line 2');

        $tiltaData = new TiltaCustomerAddressDataEntity();
        $tiltaData->__set(TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID, 'buyer-id');
        $tiltaData->__set(TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT, new DateTime());
        $tiltaData->__set(TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM, 'legal-form');
        $address->addExtension(CustomerAddressEntityExtension::TILTA_DATA, $tiltaData);

        return $address;
    }
}
