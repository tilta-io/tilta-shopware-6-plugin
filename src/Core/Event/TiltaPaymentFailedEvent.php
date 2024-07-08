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
    public function __construct(
        private readonly Exception $exception,
        private readonly OrderEntity $orderEntity,
        private readonly OrderTransactionEntity $orderTransactionEntity,
        private readonly ?CreateOrderRequestModel $orderRequestModel = null
    ) {
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
