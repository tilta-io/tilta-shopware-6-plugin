<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Event;

use Exception;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Tilta\Sdk\Model\Request\Order\CreateOrderRequestModel;

class TiltaPaymentFailedEvent
{
    private Exception $exception;

    private OrderEntity $orderEntity;

    private OrderTransactionEntity $orderTransactionEntity;

    private ?CreateOrderRequestModel $orderRequestModel;

    public function __construct(Exception $exception, OrderEntity $orderEntity, OrderTransactionEntity $orderTransactionEntity, ?CreateOrderRequestModel $orderRequestModel = null)
    {
        $this->exception = $exception;
        $this->orderEntity = $orderEntity;
        $this->orderTransactionEntity = $orderTransactionEntity;
        $this->orderRequestModel = $orderRequestModel;
    }

    public function getException(): Exception
    {
        return $this->exception;
    }

    public function getOrderEntity(): OrderEntity
    {
        return $this->orderEntity;
    }

    public function getOrderTransactionEntity(): OrderTransactionEntity
    {
        return $this->orderTransactionEntity;
    }

    /**
     * Could be null, if the request model does contain invalid data.
     * to the exception would be a `\Tilta\Sdk\Exception\Validation\InvalidFieldValueException`
     */
    public function getOrderRequestModel(): ?CreateOrderRequestModel
    {
        return $this->orderRequestModel;
    }
}
