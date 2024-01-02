<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Util;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;

class CustomerAddressHelper
{
    /**
     * @var EntityRepository<EntityCollection<CustomerAddressEntity>>
     */
    private EntityRepository $addressRepository;

    /**
     * @var EntityRepository<EntityCollection<OrderAddressEntity>>
     */
    private EntityRepository $orderAddressRepository;

    /**
     * @var EntityRepository<EntityCollection<TiltaCustomerAddressDataEntity>>
     */
    private EntityRepository $tiltaCustomerAddressDataRepository;

    /**
     * @param EntityRepository<EntityCollection<CustomerAddressEntity>> $addressRepository
     * @param EntityRepository<EntityCollection<OrderAddressEntity>> $orderAddressRepository
     * @param EntityRepository<EntityCollection<TiltaCustomerAddressDataEntity>> $tiltaCustomerAddressDataRepository
     */
    public function __construct(
        EntityRepository $addressRepository,
        EntityRepository $orderAddressRepository,
        EntityRepository $tiltaCustomerAddressDataRepository
    ) {
        $this->addressRepository = $addressRepository;
        $this->orderAddressRepository = $orderAddressRepository;
        $this->tiltaCustomerAddressDataRepository = $tiltaCustomerAddressDataRepository;
    }

    public function getCustomerAddressForOrder(OrderEntity $orderEntity, Context $context): ?CustomerAddressEntity
    {
        $customerId = $orderEntity->getOrderCustomer() instanceof OrderCustomerEntity ? $orderEntity->getOrderCustomer()->getCustomerId() : null;
        if (!$customerId) {
            return null;
        }

        $billingAddress = $orderEntity->getBillingAddress();
        if (!$billingAddress instanceof OrderAddressEntity) {
            $billingAddress = $this->orderAddressRepository->search(new Criteria([$orderEntity->getBillingAddressId()]), $context)->first();
        }

        if (!$billingAddress instanceof OrderAddressEntity) {
            return null;
        }

        $criteria = (new Criteria())
            ->addAssociation('customer')
            ->addFilter(new EqualsFilter('customerId', $customerId))
            ->addFilter(new EqualsFilter('company', $billingAddress->getCompany()))
            ->addFilter(new EqualsFilter('firstName', $billingAddress->getFirstName()))
            ->addFilter(new EqualsFilter('lastName', $billingAddress->getLastName()))
            ->addFilter(new EqualsFilter('street', $billingAddress->getStreet()))
            ->addFilter(new EqualsFilter('city', $billingAddress->getCity()))
            ->addFilter(new EqualsFilter('zipcode', $billingAddress->getZipcode()))
            ->addFilter(new EqualsFilter('additionalAddressLine1', $billingAddress->getAdditionalAddressLine1()))
            ->addFilter(new EqualsFilter('additionalAddressLine2', $billingAddress->getAdditionalAddressLine2()))
            ->addFilter(new EqualsFilter('phoneNumber', $billingAddress->getPhoneNumber()));

        // TODO validate
        $addressEntity = $this->addressRepository->search($criteria, $context)->first();

        // check is only for PHPStan
        return $addressEntity instanceof CustomerAddressEntity ? $addressEntity : null;
    }

    public function canCountryChanged(Context $context, string $addressId, string $newCountryId = null): bool
    {
        if ($newCountryId === null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter(TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID, $addressId));
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter(
                TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID,
                null
            )]));

            return $this->tiltaCustomerAddressDataRepository->searchIds($criteria, $context)->getIds() === [];
        }

        $criteria = new Criteria([$addressId]);
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter(
            implode('.', [CustomerAddressEntityExtension::TILTA_DATA, TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID]),
            null
        )]));

        /** @var CustomerAddressEntity|null $existingAddress */
        $existingAddress = $this->addressRepository->search($criteria, $context)->first();

        return !$existingAddress instanceof CustomerAddressEntity || $existingAddress->getCountryId() === $newCountryId;
    }
}
