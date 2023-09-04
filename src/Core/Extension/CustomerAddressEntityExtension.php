<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Extension;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Tilta\TiltaPaymentSW6\Core\Extension\Definition\TiltaCustomerAddressDataDefinition;

class CustomerAddressEntityExtension extends EntityExtension
{
    /**
     * @var string
     */
    public const TILTA_DATA = 'tiltaData';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField(self::TILTA_DATA, 'id', 'customer_address_id', TiltaCustomerAddressDataDefinition::class, true)
        );
    }

    public function getDefinitionClass(): string
    {
        return CustomerAddressDefinition::class;
    }
}
