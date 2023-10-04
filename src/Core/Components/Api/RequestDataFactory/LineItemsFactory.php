<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory;

use RuntimeException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Tilta\Sdk\Model\Order\LineItem;
use Tilta\TiltaPaymentSW6\Core\Util\AmountHelper;

class LineItemsFactory
{
    /**
     * @return LineItem[]
     */
    public function createFromOrder(OrderEntity $orderEntity, string $currencyCode): array
    {
        if (!$orderEntity->getLineItems() instanceof OrderLineItemCollection) {
            throw new RuntimeException('oder items has to be loaded');
        }

        $lineItems = [];
        foreach ($orderEntity->getLineItems() as $lineItem) {
            $lineItems[] = (new LineItem())
                ->setName($lineItem->getLabel())
                ->setQuantity($lineItem->getQuantity())
                ->setPrice(AmountHelper::toSdk($lineItem->getPrice() instanceof CalculatedPrice ? $lineItem->getPrice()->getUnitPrice() : 0))
                ->setCurrency($currencyCode)
                ->setCategory('') // TODO should this also be send?
            ;
        }

        return $lineItems;
    }
}
