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
use Tilta\Sdk\Model\Order;

class TiltaPaymentSuccessfulEvent
{
    private OrderEntity $orderEntity;

    private OrderTransactionEntity $orderTransactionEntity;

    private Order $order;

    public function __construct(OrderEntity $orderEntity, OrderTransactionEntity $orderTransactionEntity, Order $order)
    {
        $this->orderEntity = $orderEntity;
        $this->orderTransactionEntity = $orderTransactionEntity;
        $this->order = $order;
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
}
