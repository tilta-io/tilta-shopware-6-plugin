<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Extension\Entity;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class TiltaOrderDataEntity extends Entity
{
    /**
     * @var string
     */
    public const FIELD_ID = 'id';

    /**
     * @var string
     */
    public const FIELD_ORDER_ID = 'orderId';

    /**
     * @var string
     */
    public const FIELD_ORDER = 'order';

    /**
     * @var string
     */
    public const FIELD_ORDER_VERSION_ID = 'orderVersionId';

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

    /**
     * @var string
     */
    public const FIELD_INVOICE_NUMBER = 'invoiceNumber';

    /**
     * @var string
     */
    public const FIELD_INVOICE_EXTERNAL_ID = 'invoiceExternalId';

    protected string $id;

    protected string $orderId;

    protected ?OrderEntity $order = null;

    protected string $orderVersionId;

    protected string $orderExternalId;

    protected string $buyerExternalId;

    protected string $merchantExternalId;

    protected string $status;

    protected ?string $invoiceNumber = null;

    protected ?string $invoiceExternalId = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
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

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function getInvoiceExternalId(): ?string
    {
        return $this->invoiceExternalId;
    }
}
