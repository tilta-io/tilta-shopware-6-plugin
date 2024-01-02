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
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class EntityHelper
{
    /**
     * @var EntityRepository<EntityCollection<CurrencyEntity>>
     */
    private EntityRepository $currencyRepository;

    /**
     * @var EntityRepository<EntityCollection<CountryEntity>>
     */
    private EntityRepository $countryRepository;

    /**
     * @var EntityRepository<EntityCollection<OrderAddressEntity>>
     */
    private EntityRepository $orderAddressRepository;

    /**
     * @var EntityRepository<EntityCollection<CustomerEntity>>
     */
    private EntityRepository $customerRepository;

    /**
     * @param EntityRepository<EntityCollection<CurrencyEntity>> $currencyRepository
     * @param EntityRepository<EntityCollection<CountryEntity>> $countryRepository
     * @param EntityRepository<EntityCollection<OrderAddressEntity>> $orderAddressRepository
     * @param EntityRepository<EntityCollection<CustomerEntity>> $customerRepository
     */
    public function __construct(
        EntityRepository $currencyRepository,
        EntityRepository $countryRepository,
        EntityRepository $orderAddressRepository,
        EntityRepository $customerRepository
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->countryRepository = $countryRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @internal
     */
    public function getCurrencyCode(OrderEntity $orderEntity, Context $context): string
    {
        if ($orderEntity->getCurrency() instanceof CurrencyEntity) {
            $currency = $orderEntity->getCurrency();
        } else {
            /** @var CurrencyEntity|null $currency */
            $currency = $this->currencyRepository->search(new Criteria([$orderEntity->getCurrencyId()]), $context)->first();
        }

        if ($currency instanceof CurrencyEntity) {
            return $currency->getIsoCode();
        }

        // should never occur.
        throw new RuntimeException('Currency for order `' . $orderEntity->getId() . '` does not exist (anymore).');
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     * @internal
     */
    public function getCountryCode($addressEntity, Context $context): ?string
    {
        if ($addressEntity->getCountry() instanceof CountryEntity) {
            $country = $addressEntity->getCountry();
        } else {
            /** @var CountryEntity|null $country */
            $country = $this->countryRepository->search(new Criteria([$addressEntity->getCountryId()]), $context)->first();
        }

        if ($country instanceof CountryEntity) {
            return $country->getIso();
        }

        // should never occur.
        throw new RuntimeException('Country for order-address `' . $addressEntity->getId() . '` does not exist (anymore).');
    }

    public function getOrderAddress(string $addressId, Context $context): ?OrderAddressEntity
    {
        $orderAddress = $this->orderAddressRepository->search(new Criteria([$addressId]), $context)->first();

        // check is only for PHPStan
        return $orderAddress instanceof OrderAddressEntity ? $orderAddress : null;
    }

    public function getCustomerFromAddress(CustomerAddressEntity $customerAddressEntity, Context $context): CustomerEntity
    {
        if ($customerAddressEntity->getCustomer() instanceof CustomerEntity) {
            return $customerAddressEntity->getCustomer();
        }

        $customer = $this->customerRepository->search(new Criteria([$customerAddressEntity->getCustomerId()]), $context)->first();

        if (!$customer instanceof CustomerEntity) {
            throw new RuntimeException('customer can not be found for address');
        }

        return $customer;
    }
}
