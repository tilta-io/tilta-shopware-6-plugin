<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Exception;

use Shopware\Core\Framework\HttpException;

class MissingBuyerInformationException extends HttpException
{
    private array $messages = [];

    public function __construct(array $messages)
    {
        parent::__construct(400, 'TILTA_BUYER_MISSING_DATA', implode('\n', $messages));
        $this->messages = $messages;
    }

    public function getErrorMessages(): array
    {
        return $this->messages;
    }
}
