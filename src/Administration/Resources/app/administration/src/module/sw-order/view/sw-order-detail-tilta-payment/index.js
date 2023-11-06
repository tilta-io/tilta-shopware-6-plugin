import template from './sw-order-detail-tilta-payment.html.twig';
import './sw-order-detail-tilta-payment.scss';

const {Component} = Shopware;
const {mapState} = Component.getComponentHelper();

Component.register('sw-order-detail-tilta-payment', {
    template,

    inject: ['acl'],

    metaInfo() {
        return {
            title: 'Tilta Payment'
        };
    },

    computed: {
        ...mapState('swOrderDetail', [
            'order',
        ]),
    },
});

Shopware.Module.register('sw-order-detail-tab-tilta-payment', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'sw.order.detail.tilta-payment',
                path: '/sw/order/detail/:id/tilta-payment',
                component: 'sw-order-detail-tilta-payment',
                meta: {
                    parentPath: "sw.order.detail",
                    meta: {
                        parentPath: 'sw.order.index',
                        privilege: 'order.viewer',
                    },
                }
            });
        }
        next(currentRoute);
    }
});
