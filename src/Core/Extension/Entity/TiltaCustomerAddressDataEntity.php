<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Extension\Entity;

use DateTimeInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class TiltaCustomerAddressDataEntity extends Entity
{
    /**
     * @var string
     */
    public const FIELD_CUSTOMER_ADDRESS_ID = 'customerAddressId';

    /**
     * @var string
     */
    public const FIELD_LEGAL_FORM = 'legalForm';

    /**
     * @var string
     */
    public const FIELD_BUYER_EXTERNAL_ID = 'buyerExternalId';

    /**
     * @var string
     */
    public const FIELD_INCORPORATED_AT = 'incorporatedAt';

    /**
     * @var string
     */
    public const FIELD_TOTAL_AMOUNT = 'totalAmount';

    /**
     * @var string
     */
    public const FIELD_VALID_UNTIL = 'validUntil';

    protected string $customerAddressId;

    protected string $legalForm;

    protected ?string $buyerExternalId = null;

    protected ?int $totalAmount = null;

    protected ?DateTimeInterface $validUntil = null;

    protected DateTimeInterface $incorporatedAt;

    public function getCustomerAddressId(): string
    {
        return $this->customerAddressId;
    }

    public function getLegalForm(): string
    {
        return $this->legalForm;
    }

    public function getBuyerExternalId(): ?string
    {
        return $this->buyerExternalId;
    }

    public function getIncorporatedAt(): DateTimeInterface
    {
        return $this->incorporatedAt;
    }

    public function getTotalAmount(): ?int
    {
        return $this->totalAmount;
    }

    public function getValidUntil(): ?DateTimeInterface
    {
        return $this->validUntil;
    }
}
