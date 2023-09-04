<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Components\Api\RequestDataFactory\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Tilta\Sdk\Model\Request\Order\CreateOrderRequestModel;

class CreateOrderRequestModelBuiltEvent
{
    private OrderEntity $orderEntity;

    private CreateOrderRequestModel $model;

    public function __construct(OrderEntity $orderEntity, CreateOrderRequestModel $model)
    {
        $this->orderEntity = $orderEntity;
        $this->model = $model;
    }

    public function getModel(): CreateOrderRequestModel
    {
        return $this->model;
    }

    public function setModel(CreateOrderRequestModel $model): void
    {
        $this->model = $model;
    }

    public function getOrderEntity(): OrderEntity
    {
        return $this->orderEntity;
    }
}
