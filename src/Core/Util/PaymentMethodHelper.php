<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Util;

use InvalidArgumentException;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaPaymentMethod;

class PaymentMethodHelper
{
    /**
     * @param PaymentMethodEntity|PaymentHandlerInterface|string $method
     */
    public static function isTiltaPaymentMethod($method): bool
    {
        if (is_string($method)) {
            return is_subclass_of($method, TiltaPaymentMethod::class);
        } elseif ($method instanceof PaymentHandlerInterface) {
            return $method instanceof TiltaPaymentMethod;
        } elseif ($method instanceof PaymentMethodEntity) {
            return self::isTiltaPaymentMethod($method->getHandlerIdentifier());
        }

        /** @phpstan-ignore-next-line */
        throw new InvalidArgumentException('argument $method must be one of string, PaymentHandlerInterface or PaymentMethodEntity.');
    }
}
