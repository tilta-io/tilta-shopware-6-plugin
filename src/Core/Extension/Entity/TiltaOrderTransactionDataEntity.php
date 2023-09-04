<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Extension\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class TiltaOrderTransactionDataEntity extends Entity
{
    /**
     * @var string
     */
    public const FIELD_ORDER_TRANSACTION_ID = 'orderTransactionId';

    /**
     * @var string
     */
    public const FIELD_ORDER_EXTERNAL_ID = 'orderExternalId';

    /**
     * @var string
     */
    public const FIELD_BUYER_EXTERNAL_ID = 'buyerExternalId';

    /**
     * @var string
     */
    public const FIELD_MERCHANT_EXTERNAL_ID = 'merchantExternalId';

    /**
     * @var string
     */
    public const FIELD_STATUS = 'status';

    protected string $orderTransactionId;

    protected string $orderExternalId;

    protected string $buyerExternalId;

    protected string $merchantExternalId;

    protected string $status;

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function getOrderExternalId(): string
    {
        return $this->orderExternalId;
    }

    public function getBuyerExternalId(): string
    {
        return $this->buyerExternalId;
    }

    public function getMerchantExternalId(): string
    {
        return $this->merchantExternalId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
