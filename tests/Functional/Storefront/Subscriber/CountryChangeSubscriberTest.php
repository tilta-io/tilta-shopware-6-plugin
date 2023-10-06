<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Tests\Storefront\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPage;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Storefront\Subscriber\CountryChangeSubscriber;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class CountryChangeSubscriberTest extends TestCase
{
    use TiltaTestBehavior;

    private string $initialCountryId;

    protected function setUp(): void
    {
        $this->initialCountryId = $this->getDeCountryId();
    }

    public function testIfCountriesKept()
    {
        $addressHelper = $this->createMock(CustomerAddressHelper::class);
        $addressHelper->method('canCountryChanged')->willReturn(true);

        $subscriber = new CountryChangeSubscriber($addressHelper);

        $event = new AddressDetailPageLoadedEvent(
            new AddressDetailPage(),
            $this->createMock(SalesChannelContext::class),
            new Request()
        );

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setCountryId($this->initialCountryId);

        $event->getPage()->setAddress($customerAddress);
        $event->getPage()->setCountries($this->getCountryCollection());

        $subscriber->onAddressPageLoaded($event);

        static::assertEquals(3, $event->getPage()->getCountries()->count(), 'No countries should be removed, because the customer does not have any tilta data.');
    }

    public function testIfCountriesRemoved()
    {
        $addressHelper = $this->createMock(CustomerAddressHelper::class);
        $addressHelper->method('canCountryChanged')->willReturn(false);

        $subscriber = new CountryChangeSubscriber($addressHelper);

        $event = new AddressDetailPageLoadedEvent(
            new AddressDetailPage(),
            $this->createMock(SalesChannelContext::class),
            new Request()
        );

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setId(Uuid::randomHex());
        $customerAddress->setCountryId($this->initialCountryId);

        $event->getPage()->setAddress($customerAddress);
        $event->getPage()->setCountries($this->getCountryCollection());

        $subscriber->onAddressPageLoaded($event);

        static::assertEquals(1, $event->getPage()->getCountries()->count(), 'All countries, which are not the actual country, should be removed.');
        static::assertInstanceOf(CountryEntity::class, $event->getPage()->getCountries()->first());
        static::assertEquals($this->initialCountryId, $event->getPage()->getCountries()->first()->getId());
    }

    private function getCountryCollection(): CountryCollection
    {
        $countryDE = new CountryEntity();
        $countryDE->setId($this->initialCountryId);
        $countryDE->setIso('DE');

        $countryAT = new CountryEntity();
        $countryAT->setId(Uuid::randomHex());
        $countryAT->setIso('AT');

        $countryCH = new CountryEntity();
        $countryCH->setId(Uuid::randomHex());
        $countryCH->setIso('CH');

        return new CountryCollection([
            $countryDE,
            $countryAT,
            $countryCH,
        ]);
    }
}
