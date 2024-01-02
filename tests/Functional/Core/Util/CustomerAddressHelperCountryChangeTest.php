<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class CustomerAddressHelperCountryChangeTest extends TestCase
{
    use TiltaTestBehavior;

    private CustomerEntity $customer;

    private CustomerAddressHelper $helper;

    protected function setUp(): void
    {
        $this->customer = $this->createCustomer();

        $this->helper = $this->getContainer()->get(CustomerAddressHelper::class);
    }

    public function testIfReturnTrueIfBlankCustomer()
    {
        $context = Context::createDefaultContext();

        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId()), 'Changing the country should be possible, because the customer does not have any tilta data.');
        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId(), Uuid::randomHex()), 'Changing the country should be possible, because the customer does not have any tilta data.');
        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId(), $this->getDeCountryId()), 'Changing to the same country as actual should be always possible.');
    }

    public function testIfReturnTrueIfNoBuyerId()
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('tilta_address_data.repository');
        $repo->upsert([[
            TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $this->customer->getDefaultBillingAddressId(),
            TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'DE_GMBH',
            TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new \DateTime(),
        ]], $context);

        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId()), 'Changing the country should be possible, because the customer does not have any tilta data.');
        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId(), Uuid::randomHex()), 'Changing the country should be possible, because the customer does not have any tilta data.');
        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId(), $this->getDeCountryId()), 'Changing to the same country as actutal should be always possible.');
    }

    public function testIfReturnFalseIfHasBuyerId()
    {
        $context = Context::createDefaultContext();

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('tilta_address_data.repository');
        $repo->upsert([[
            TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $this->customer->getDefaultBillingAddressId(),
            TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'DE_GMBH',
            TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new \DateTime(),
            TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => 'test-company',
        ]], $context);

        static::assertFalse($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId()), 'Changing the country should be prevent, because the customer does have a buyer id.');
        static::assertFalse($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId(), Uuid::randomHex()), 'Changing the country should be prevent, because the customer does have a buyer id.');
        static::assertTrue($this->helper->canCountryChanged($context, $this->customer->getDefaultBillingAddressId(), $this->getDeCountryId()), 'Changing to the same country as actual should be always possible.');
    }
}
