<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Storefront\Subscriber;

use RuntimeException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tilta\TiltaPaymentSW6\Core\Routes\TiltaCheckoutDataRoute;
use Tilta\TiltaPaymentSW6\Core\Util\PaymentMethodHelper;

class CheckoutSubscriber implements EventSubscriberInterface
{
    private TiltaCheckoutDataRoute $checkoutDataRoute;

    public function __construct(
        TiltaCheckoutDataRoute $checkoutDataRoute
    ) {
        $this->checkoutDataRoute = $checkoutDataRoute;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => ['addTiltaData', 320],
            AccountEditOrderPageLoadedEvent::class => ['addTiltaData', 320],
        ];
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent|AccountEditOrderPageLoadedEvent $event
     */
    public function addTiltaData(PageLoadedEvent $event): void
    {
        $paymentMethod = $event->getSalesChannelContext()->getPaymentMethod();
        if (!PaymentMethodHelper::isTiltaPaymentMethod($paymentMethod) || !$event->getPage()->getPaymentMethods()->has($paymentMethod->getId())) {
            return;
        }

        if ($event instanceof CheckoutConfirmPageLoadedEvent) {
            $tiltaData = $this->checkoutDataRoute->getCheckoutDataForSalesChannelContext($event->getSalesChannelContext(), new RequestDataBag($event->getRequest()->request->all()));
        } elseif ($event instanceof AccountEditOrderPageLoadedEvent) {
            $tiltaData = $this->checkoutDataRoute->getCheckoutDataForOrderEntity($event->getSalesChannelContext(), $event->getPage()->getOrder(), new RequestDataBag($event->getRequest()->request->all()));
        } else {
            throw new RuntimeException('not supported event: ' . get_class($event));
        }

        if ($tiltaData instanceof ArrayStruct) {
            $event->getPage()->addExtension('tilta', $tiltaData);
        }
    }
}
