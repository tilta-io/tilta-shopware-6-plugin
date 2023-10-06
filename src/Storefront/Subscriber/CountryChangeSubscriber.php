<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Storefront\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;

class CountryChangeSubscriber implements EventSubscriberInterface
{
    private CustomerAddressHelper $addressHelper;

    public function __construct(
        CustomerAddressHelper $addressHelper
    ) {
        $this->addressHelper = $addressHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AddressDetailPageLoadedEvent::class => 'onAddressPageLoaded',
        ];
    }

    public function onAddressPageLoaded(AddressDetailPageLoadedEvent $event): void
    {
        $address = $event->getPage()->getAddress();
        if (!$address instanceof CustomerAddressEntity || $this->addressHelper->canCountryChanged($address->getId())) {
            return;
        }

        $countries = $event->getPage()->getCountries();

        $event->getPage()->setCountries($countries->filter(static fn (CountryEntity $entity): bool => $entity->getId() === $address->getCountryId()));

        $event->getPage()->assign([
            'tiltaNoticeCountryNotChangeable' => true,
        ]);
    }
}
