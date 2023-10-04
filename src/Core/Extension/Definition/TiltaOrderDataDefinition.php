<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Extension\Definition;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderDataEntity;

class TiltaOrderDataDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'tilta_order_data';
    }

    public function getEntityClass(): string
    {
        return TiltaOrderDataEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', TiltaOrderDataEntity::FIELD_ID))->addFlags(new Required(), new PrimaryKey()),
            new VersionField(),
            (new FkField('order_id', TiltaOrderDataEntity::FIELD_ORDER_ID, OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class, 'order_version_id'))->addFlags(new Required()),
            (new StringField('order_external_id', TiltaOrderDataEntity::FIELD_ORDER_EXTERNAL_ID))->addFlags(new Required()),
            (new StringField('buyer_external_id', TiltaOrderDataEntity::FIELD_BUYER_EXTERNAL_ID))->addFlags(new Required()),
            (new StringField('merchant_external_id', TiltaOrderDataEntity::FIELD_MERCHANT_EXTERNAL_ID))->addFlags(new Required()),
            (new StringField('invoice_number', TiltaOrderDataEntity::FIELD_INVOICE_NUMBER)),
            (new StringField('invoice_external_id', TiltaOrderDataEntity::FIELD_INVOICE_EXTERNAL_ID)),
            (new StringField('status', TiltaOrderDataEntity::FIELD_STATUS))->addFlags(new Required()),
            new OneToOneAssociationField(TiltaOrderDataEntity::FIELD_ORDER, 'order_id', 'id', OrderDefinition::class, false),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
