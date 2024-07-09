<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class MissingBuyerInformationException extends ShopwareHttpException
{
    public function __construct(array $messages)
    {
        parent::__construct('There are missing fields', [
            'errors' => $messages,
        ]);
    }

    public function getErrorMessages(): array
    {
        $errors = $this->getParameter('errors');

        return is_array($errors) ? $errors : [];
    }

    public function getErrorCode(): string
    {
        return 'TILTA_BUYER_MISSING_DATA';
    }
}
