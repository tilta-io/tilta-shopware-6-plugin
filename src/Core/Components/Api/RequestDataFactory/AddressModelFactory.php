<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\ExtendableInterface;
use Tilta\Sdk\Model\Address;
use Tilta\Sdk\Util\AddressHelper;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;

class AddressModelFactory
{
    private EntityHelper $entityHelper;

    public function __construct(EntityHelper $entityHelper)
    {
        $this->entityHelper = $entityHelper;
    }

    public function createFromOrderAddress(OrderAddressEntity $addressEntity, Context $context): Address
    {
        return $this->createTiltaAddressFromAddress($addressEntity, $context);
    }

    public function createFromCustomerAddress(CustomerAddressEntity $addressEntity, Context $context): Address
    {
        return $this->createTiltaAddressFromAddress($addressEntity, $context);
    }

    /**
     * @param CustomerAddressEntity|OrderAddressEntity $addressEntity
     */
    private function createTiltaAddressFromAddress(ExtendableInterface $addressEntity, Context $context): Address
    {
        return (new Address())
            ->setStreet(AddressHelper::getStreetName($addressEntity->getStreet()) ?: '')
            ->setHouseNumber(AddressHelper::getHouseNumber($addressEntity->getStreet()) ?: '')
            ->setPostcode($addressEntity->getZipcode())
            ->setCity($addressEntity->getCity())
            ->setCountry($this->entityHelper->getCountryCode($addressEntity, $context) ?: '')
            ->setAdditional(self::mergeAdditionalAddressLines($addressEntity));
    }

    /**
     * @param OrderAddressEntity|CustomerAddressEntity $addressEntity
     */
    private function mergeAdditionalAddressLines($addressEntity): ?string
    {
        $additionalLines = array_filter([$addressEntity->getAdditionalAddressLine1(), $addressEntity->getAdditionalAddressLine2()], static fn ($value): bool => !empty($value));

        return $additionalLines !== [] ? implode("\n", $additionalLines) : null;
    }
}
