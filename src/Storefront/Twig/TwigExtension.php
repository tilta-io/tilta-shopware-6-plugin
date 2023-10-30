<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Storefront\Twig;

use Shopware\Core\Checkout\Order\OrderEntity;
use Tilta\TiltaPaymentSW6\Core\Util\OrderHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('tiltaPaymentMethodChangeable', fn (OrderEntity $orderEntity): bool => $this->isPaymentMethodChangeable($orderEntity)),
        ];
    }

    public function isPaymentMethodChangeable(OrderEntity $orderEntity): bool
    {
        return OrderHelper::isPaymentChangeable($orderEntity);
    }
}
