<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Util;

use RuntimeException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class EntityHelper
{
    private EntityRepository $currencyRepository;

    private EntityRepository $countryRepository;

    private EntityRepository $orderAddressRepository;

    public function __construct(
        EntityRepository $currencyRepository, // resolved by exact name
        EntityRepository $countryRepository,  // resolved by exact name
        EntityRepository $orderAddressRepository // resolved by exact name
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->countryRepository = $countryRepository;
        $this->orderAddressRepository = $orderAddressRepository;
    }

    /**
     * @internal
     */
    public function getCurrencyCode(OrderEntity $orderEntity): string
    {
        if ($orderEntity->getCurrency() instanceof CurrencyEntity) {
            $currency = $orderEntity->getCurrency();
        } else {
            /** @var CurrencyEntity|null $currency */
            $currency = $this->currencyRepository->search(new Criteria([$orderEntity->getCurrencyId()]), Context::createDefaultContext())->first();
        }

        if ($currency instanceof CurrencyEntity) {
            return $currency->getIsoCode();
        }

        // should never occur.
        throw new RuntimeException('Currency for order `' . $orderEntity->getId() . '` does not exist (anymore).');
    }

    /**
     * @internal
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     */
    public function getCountryCode($addressEntity): ?string
    {
        if ($addressEntity->getCountry() instanceof CountryEntity) {
            $country = $addressEntity->getCountry();
        } else {
            /** @var CountryEntity|null $country */
            $country = $this->countryRepository->search(new Criteria([$addressEntity->getCountryId()]), Context::createDefaultContext())->first();
        }

        if ($country instanceof CountryEntity) {
            return $country->getIso();
        }

        // should never occur.
        throw new RuntimeException('Country for order-address `' . $addressEntity->getId() . '` does not exist (anymore).');
    }

    public function getOrderAddress(string $addressId): ?OrderAddressEntity
    {
        return $this->orderAddressRepository->search(new Criteria([$addressId]), Context::createDefaultContext())->first();
    }
}
