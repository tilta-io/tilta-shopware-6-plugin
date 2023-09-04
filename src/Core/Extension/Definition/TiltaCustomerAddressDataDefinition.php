<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Extension\Definition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;

class TiltaCustomerAddressDataDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'tilta_address_data';
    }

    public function getEntityClass(): string
    {
        return TiltaCustomerAddressDataEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('customer_address_id', TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('legal_form', TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM))->addFlags(new Required()),
            (new StringField('buyer_external_id', TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID)),
            (new DateField('incorporated_at', TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT))->addFlags(new Required()),
            (new DateField('valid_until', TiltaCustomerAddressDataEntity::FIELD_VALID_UNTIL)),
            (new IntField('total_amount', TiltaCustomerAddressDataEntity::FIELD_TOTAL_AMOUNT)),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
