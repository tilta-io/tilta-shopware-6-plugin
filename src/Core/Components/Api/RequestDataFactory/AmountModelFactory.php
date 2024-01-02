<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Tilta\Sdk\Model\Amount;
use Tilta\TiltaPaymentSW6\Core\Util\AmountHelper;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;

class AmountModelFactory
{
    private EntityHelper $entityHelper;

    public function __construct(EntityHelper $entityHelper)
    {
        $this->entityHelper = $entityHelper;
    }

    public function createAmountForOrder(OrderEntity $orderEntity, Context $context): Amount
    {
        return (new Amount())
            ->setNet(AmountHelper::toSdk($orderEntity->getAmountNet()))
            ->setGross(AmountHelper::toSdk($orderEntity->getAmountTotal()))
            ->setTax(AmountHelper::toSdk($orderEntity->getPrice()->getCalculatedTaxes()->getAmount()))
            ->setCurrency($this->entityHelper->getCurrencyCode($orderEntity, $context));
    }
}
