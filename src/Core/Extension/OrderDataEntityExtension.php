<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Extension;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Tilta\TiltaPaymentSW6\Core\Extension\Definition\TiltaOrderDataDefinition;

class OrderDataEntityExtension extends EntityExtension
{
    /**
     * @var string
     */
    public const EXTENSION_NAME = 'tiltaData';

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                self::EXTENSION_NAME,
                'id',
                'order_id',
                TiltaOrderDataDefinition::class,
                true
            ))->addFlags(new RestrictDelete(), new CascadeDelete())
        );
    }

    public function getDefinitionClass(): string
    {
        return OrderDefinition::class;
    }
}
