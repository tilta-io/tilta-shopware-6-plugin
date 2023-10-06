<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Subscriber;

use DateTime;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class CountryChangeSubscriberTest extends TestCase
{
    use TiltaTestBehavior;

    private CustomerEntity $customer;

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
                TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'DE_GMBH',
                TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new DateTime(),
            ],
        ]], Context::createDefaultContext());
    }

    public function testIfCountryChangedGotBlocked()
    {
        $this->expectException(WriteException::class);
        $this->expectExceptionMessageMatches('/.*Changing the country on a existing customer-address is not allowed.*/');

        /** @var EntityRepository $addressRepository */
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->update([[
            'id' => $this->customer->getDefaultBillingAddressId(),
            'countryId' => $this->getNotDECountry(),
        ]], Context::createDefaultContext());
    }

    public function testIfCountryChangedGotNotBlockedIfNoCountryGotChanged()
    {
        /** @var EntityRepository $addressRepository */
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->update([[
            'id' => $this->customer->getDefaultBillingAddressId(),
            'firstname' => 'another name',
        ]], Context::createDefaultContext());
        static::assertTrue(true);
    }

    public function testIfCountryChangedGotNotBlockedIfCountryGotChangedToTheSame()
    {
        /** @var EntityRepository $addressRepository */
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->update([[
            'id' => $this->customer->getDefaultBillingAddressId(),
            'countryId' => $this->getDeCountryId(),
        ]], Context::createDefaultContext());
        static::assertTrue(true);
    }

    public function testIfCountryChangedGotBlockedBecauseNoTiltaData()
    {
        /** @var EntityRepository $addressRepository */
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->update([[
            'id' => $this->customer->getDefaultBillingAddressId(),
            'tiltaData' => [],
        ]], Context::createDefaultContext());

        $addressRepository->update([[
            'id' => $this->createCustomer()->getDefaultBillingAddressId(),
            'countryId' => $this->getNotDECountry(),
        ]], Context::createDefaultContext());

        static::assertTrue(true);
    }

    public function testIfCountryChangedGotBlockedBecauseEmptyTiltaData()
    {
        /** @var EntityRepository $addressRepository */
        $addressRepository = $this->getContainer()->get('customer_address.repository');
        $addressRepository->update([[
            'id' => $this->customer->getDefaultBillingAddressId(),
            'tiltaData' => [
                TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $this->customer->getDefaultBillingAddressId(),
                TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new DateTime(),
                TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'DE_GMBH',
            ],
        ]], Context::createDefaultContext());

        $addressRepository->update([[
            'id' => $this->createCustomer()->getDefaultBillingAddressId(),
            'countryId' => $this->getNotDECountry(),
        ]], Context::createDefaultContext());

        static::assertTrue(true);
    }

    protected function getNotDECountry(): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(1)
            ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('iso', 'DE')]));

        /** @var string $id */
        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();

        return $id;
    }
}
