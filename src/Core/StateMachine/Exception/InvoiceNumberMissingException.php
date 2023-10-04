<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\StateMachine\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvoiceNumberMissingException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'It is not allowed to change the delivery status if no invoice number is available.'
        );
    }

    public function getErrorCode(): string
    {
        return 'TILTA__INVOICE_NUMBER_MISSING';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
