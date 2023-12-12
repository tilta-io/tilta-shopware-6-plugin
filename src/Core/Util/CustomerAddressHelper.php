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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CustomerAddressHelper
{
    private EntityRepository $addressRepository;

    private EntityRepository $orderAddressRepository;

    public function __construct(
        EntityRepository $addressRepository,
        EntityRepository $orderAddressRepository
    ) {
        $this->addressRepository = $addressRepository;
        $this->orderAddressRepository = $orderAddressRepository;
    }

    public function getCustomerAddressForOrder(OrderEntity $orderEntity): ?CustomerAddressEntity
    {
        $customerId = $orderEntity->getOrderCustomer() instanceof OrderCustomerEntity ? $orderEntity->getOrderCustomer()->getCustomerId() : null;
        if (!$customerId) {
            return null;
        }

        $billingAddress = $orderEntity->getBillingAddress();
        if (!$billingAddress instanceof OrderAddressEntity) {
            $billingAddress = $this->orderAddressRepository->search(new Criteria([$orderEntity->getBillingAddressId()]), Context::createDefaultContext())->first();
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
        return $this->addressRepository->search($criteria, Context::createDefaultContext())->first();
    }
}
