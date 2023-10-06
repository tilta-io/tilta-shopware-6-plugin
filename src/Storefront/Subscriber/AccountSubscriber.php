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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            HandlePaymentMethodRouteRequestEvent::class => 'onHandlePaymentMethodRouteRequest',
        ];
    }

    public function onHandlePaymentMethodRouteRequest(HandlePaymentMethodRouteRequestEvent $event): void
    {
        // it is required that we forward the tilta-data form the storefront-request to the store-api request.
        // without that, the payment handler will not receive the data.
        if ($event->getStorefrontRequest()->request->has('tilta')) {
            $event->getStoreApiRequest()->request->set(
                'tilta',
                $event->getStorefrontRequest()->request->get('tilta')
            );
        }
    }
}
