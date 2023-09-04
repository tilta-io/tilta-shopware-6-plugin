<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Util;

class AmountHelper
{
    public static function toSdk(float $price): int
    {
        return (int) round($price * 100, 0);
    }

    public static function fromSdk(int $price): float
    {
        return $price / 100;
    }
}
