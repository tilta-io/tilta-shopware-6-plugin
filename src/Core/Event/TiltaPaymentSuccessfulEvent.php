<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\Sdk\Model\Order;

class TiltaPaymentSuccessfulEvent
{
    public function __construct(
        private readonly OrderEntity $orderEntity,
        private readonly OrderTransactionEntity $orderTransactionEntity,
        private readonly Order $order,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getOrderEntity(): OrderEntity
    {
        return $this->orderEntity;
    }

    public function getOrderTransactionEntity(): OrderTransactionEntity
    {
        return $this->orderTransactionEntity;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
