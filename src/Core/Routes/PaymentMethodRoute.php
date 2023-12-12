<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Core\Util\PaymentMethodHelper;

class PaymentMethodRoute extends AbstractPaymentMethodRoute
{
    private ConfigService $configService;

    private AbstractPaymentMethodRoute $innerService;

    private RequestStack $requestStack;

    /**
     * @var EntityRepository<EntityCollection<OrderEntity>>
     */
    private EntityRepository $orderRepository;

    private CustomerAddressHelper $customerAddressHelper;

    private CartService $cartService;

    private FacilityService $facilityService;

    private AbstractContextSwitchRoute $contextSwitchRoute;

    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        AbstractPaymentMethodRoute $innerService,
        ConfigService $configService,
        RequestStack $requestStack,
        EntityRepository $orderRepository,
        CustomerAddressHelper $customerAddressHelper,
        CartService $cartService,
        FacilityService $facilityService,
        AbstractContextSwitchRoute $contextSwitchRoute
    ) {
        $this->innerService = $innerService;
        $this->requestStack = $requestStack;
        $this->configService = $configService;
        $this->orderRepository = $orderRepository;
        $this->customerAddressHelper = $customerAddressHelper;
        $this->cartService = $cartService;
        $this->facilityService = $facilityService;
        $this->contextSwitchRoute = $contextSwitchRoute;
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $response = $this->innerService->load($request, $context, $criteria);

        $currentRequest = $this->requestStack->getCurrentRequest();
        if (!$currentRequest instanceof Request) {
            return $response;
        }

        $filterMethods = $request->query->getBoolean('onlyAvailable', false);
        if (!$filterMethods) {
            return $response;
        }

        if (!$this->configService->isConfigReady()) {
            return $this->removeAllTiltaMethods($response, $context);
        }

        // if the order id is set, the oder has been already placed, and the customer may try to change/edit
        // the payment method. - e.g. in case of a failed payment
        $orderId = $currentRequest->get('orderId');
        if (is_string($orderId)) {
            $criteria = (new Criteria([$orderId]))
                ->addAssociation('currency')
                ->addAssociation('addresses');

            /** @var OrderEntity $order */
            $order = $this->orderRepository->search($criteria, $context->getContext())->first();
            // info: we can only create a facility for a customer-address to make sure the customer is registered
            // we are trying to fetch the customer-address entity, if it is equal to the order-address
            $billingAddress = $this->customerAddressHelper->getCustomerAddressForOrder($order);
            $price = $order->getPrice();
        } else {
            $customer = $context->getCustomer();
            $billingAddress = $customer instanceof CustomerEntity && !$customer->getGuest() ? $customer->getActiveBillingAddress() : null;
            $price = $this->cartService->getCart($context->getToken(), $context)->getPrice();
        }

        if (!$billingAddress instanceof CustomerAddressEntity || $this->shouldRemovePaymentMethods($billingAddress, $price)) {
            return $this->removeAllTiltaMethods($response, $context);
        }

        return $response;
    }

    private function shouldRemovePaymentMethods(CustomerAddressEntity $customerAddress, CartPrice $price): bool
    {
        if ($customerAddress->getCompany() === null || trim($customerAddress->getCompany()) === '') {
            return true;
        }

        // total amount of facility is lower than order amount -> customer can not buy
        return !$this->facilityService->checkCartAmount($customerAddress, $price);
    }

    private function removeAllTiltaMethods(PaymentMethodRouteResponse $response, SalesChannelContext $context): PaymentMethodRouteResponse
    {
        foreach ($response->getPaymentMethods() as $key => $paymentMethod) {
            if (PaymentMethodHelper::isTiltaPaymentMethod($paymentMethod) && is_string($key)) {
                $response->getPaymentMethods()->remove($key);
            }
        }

        if (PaymentMethodHelper::isTiltaPaymentMethod($context->getPaymentMethod())) {
            $newPaymentMethod = $response->getPaymentMethods()->first();
            if ($newPaymentMethod instanceof PaymentMethodEntity) {
                $this->contextSwitchRoute->switchContext(new RequestDataBag([
                    SalesChannelContextService::PAYMENT_METHOD_ID => $newPaymentMethod->getId(),
                ]), $context);
            }
        }

        return $response;
    }
}
