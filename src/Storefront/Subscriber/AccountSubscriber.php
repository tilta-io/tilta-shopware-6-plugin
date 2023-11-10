<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Storefront\Subscriber;

use Shopware\Storefront\Event\RouteRequest\HandlePaymentMethodRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tilta\TiltaPaymentSW6\Core\Util\OrderHelper;

class AccountSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            HandlePaymentMethodRouteRequestEvent::class => 'onHandlePaymentMethodRouteRequest',
            AccountEditOrderPageLoadedEvent::class => ['onEditOrderPageLoaded', 330],
        ];
    }

    public function onEditOrderPageLoaded(AccountEditOrderPageLoadedEvent $event): void
    {
        if ($event->getPage()->isPaymentChangeable() && !OrderHelper::isPaymentChangeable($event->getPage()->getOrder())) {
            $event->getPage()->setPaymentChangeable(false);
        }
    }

    public function onHandlePaymentMethodRouteRequest(HandlePaymentMethodRouteRequestEvent $event): void
    {
        // it is required that we forward the tilta-data form the storefront-request to the store-api request.
        // without that, the payment handler will not receive the data.
        if ($event->getStorefrontRequest()->request->has('tilta')) {
            $event->getStoreApiRequest()->request->set(
                'tilta',
                $event->getStorefrontRequest()->request->all()['tilta'] ?? []
            );
        }
    }
}
