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
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaOrderTransactionDataEntity;

class TiltaOrderTransactionDataDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'tilta_order_transaction';
    }

    public function getEntityClass(): string
    {
        return TiltaOrderTransactionDataEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('order_transaction_id', TiltaOrderTransactionDataEntity::FIELD_ORDER_TRANSACTION_ID))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('order_external_id', TiltaOrderTransactionDataEntity::FIELD_ORDER_EXTERNAL_ID))->addFlags(new Required()),
            (new StringField('buyer_external_id', TiltaOrderTransactionDataEntity::FIELD_BUYER_EXTERNAL_ID))->addFlags(new Required()),
            (new StringField('merchant_external_id', TiltaOrderTransactionDataEntity::FIELD_MERCHANT_EXTERNAL_ID))->addFlags(new Required()),
            (new StringField('status', TiltaOrderTransactionDataEntity::FIELD_STATUS))->addFlags(new Required()),
        ]);
    }

    protected function defaultFields(): array
    {
        return [];
    }
}
