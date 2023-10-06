<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\Sdk\Exception\GatewayException\Facility\FacilityExceededException;
use Tilta\Sdk\Exception\GatewayException\Facility\NoActiveFacilityFoundException;
use Tilta\Sdk\Exception\GatewayException\NotFoundException\BuyerNotFoundException;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Response\Order\GetPaymentTermsResponseModel;
use Tilta\Sdk\Model\Response\Order\PaymentTerm\PaymentTerm;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Service\PaymentTermsService;
use Tilta\TiltaPaymentSW6\Core\Util\AmountHelper;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Core\Util\PaymentMethodHelper;

class TiltaCheckoutDataRoute
{
    private PaymentTermsService $paymentTermsService;

    private BuyerRequestFormDataRoute $buyerRequestFormDataRoute;

    private CustomerAddressHelper $customerAddressHelper;

    private FacilityService $facilityService;

    private CartService $cartService;

    private LoggerInterface $logger;

    public function __construct(
        PaymentTermsService $paymentTermsService,
        BuyerRequestFormDataRoute $buyerRequestFormDataRoute,
        CustomerAddressHelper $customerAddressHelper,
        FacilityService $facilityService,
        CartService $cartService,
        LoggerInterface $logger
    ) {
        $this->paymentTermsService = $paymentTermsService;
        $this->buyerRequestFormDataRoute = $buyerRequestFormDataRoute;
        $this->customerAddressHelper = $customerAddressHelper;
        $this->facilityService = $facilityService;
        $this->cartService = $cartService;
        $this->logger = $logger;
    }

    public function getCheckoutDataForSalesChannelContext(SalesChannelContext $context, RequestDataBag $initialDataForForm = null): ?ArrayStruct
    {
        $paymentMethod = $context->getPaymentMethod();
        if (!PaymentMethodHelper::isTiltaPaymentMethod($paymentMethod)) {
            return null;
        }

        $customer = $context->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return null;
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // address is always loaded
        /** @var CustomerAddressEntity $customerAddress */
        $customerAddress = $customer->getActiveBillingAddress();

        return $this->getStructData(
            fn (): ?GetPaymentTermsResponseModel => $this->paymentTermsService->getPaymentTermsForCart($context, $cart),
            $context,
            $customerAddress,
            $cart->getPrice(),
            $initialDataForForm
        );
    }

    public function getCheckoutDataForOrderEntity(SalesChannelContext $context, OrderEntity $order, RequestDataBag $initialDataForForm = null): ?ArrayStruct
    {
        $customerAddress = $this->customerAddressHelper->getCustomerAddressForOrder($order);
        if (!$customerAddress instanceof CustomerAddressEntity) {
            return null;
        }

        return $this->getStructData(
            fn (): ?GetPaymentTermsResponseModel => $this->paymentTermsService->getPaymentTermsForOrder($order),
            $context,
            $customerAddress,
            $order->getPrice(),
            $initialDataForForm
        );
    }

    private function getStructData(
        callable $getPaymentTermsCall,
        SalesChannelContext $context,
        CustomerAddressEntity $customerAddress,
        CartPrice $totalAmount,
        RequestDataBag $initialDataForForm = null
    ): ArrayStruct {
        $initialDataForForm ??= new RequestDataBag();
        $extensionData = new ArrayStruct();

        $logDefaultContext = [
            'customer_id' => $customerAddress->getCustomerId(),
            'customer_address_id' => $customerAddress->getId(),
            'buyer_external_id' => BuyerService::getBuyerExternalId($customerAddress),
            'cart_total_amount' => $totalAmount->getTotalPrice(),
        ];

        try {
            /** @var GetPaymentTermsResponseModel|null $paymentTerms */
            $paymentTerms = $getPaymentTermsCall();

            if ($paymentTerms instanceof GetPaymentTermsResponseModel) {
                /** @var string[] $terms */
                $terms = array_map(static fn (PaymentTerm $term): array => array_merge($term->toArray(), [
                    'days' => $term->getDueDate()->diff((new DateTime())->setTime(0, 0))->days,
                ]), $paymentTerms->getPaymentTerms());

                $extensionData->set('allowedPaymentMethods', $terms);
                $extensionData->set('buyerExternalId', BuyerService::getBuyerExternalId($customerAddress));
            } else {
                $extensionData->set('action', 'buyer-registration-required');
            }
        } catch (BuyerNotFoundException $buyerNotFoundException) {
            // address does have a buyer-external-id, but it seems like that this ID is invalid. -> buyer can not buy
            $this->logger->error('Customer does have a buyer-external-id, but this ID is invalid/does not exist on Tilta gateway.', $logDefaultContext);
            $extensionData->set('error', 'unknown-error');
        } catch (NoActiveFacilityFoundException $noActiveFacilityFoundException) {
            // buyer does exist, but do not have a facility -> buyer can buy (if the user creates a facility during checkout)
            $extensionData->set('action', 'facility-request-required');
        } catch (FacilityExceededException $facilityExceededException) {
            $extensionData->set('error', 'facility-exceeded');

            try {
                $facility = $this->facilityService->getFacility($customerAddress);
                if ($facility && $facility->getTotalAmount() < AmountHelper::toSdk($totalAmount->getTotalPrice())) {
                    // facility is not exceeded, the facility is too low to be get used for this order.
                    $extensionData->set('error', 'facility-too-low');
                }
            } catch (Exception $exception) {
                // should never happen because the buyer does have a facility.
                $extensionData->set('error', 'unknown-error');
                $this->logger->error('Error during fetching actual facility for buyer to validate if the facility is to low for the order. ' . $exception->getMessage(), $logDefaultContext);
            }
        } catch (TiltaException $tiltaException) {
            // there seems to be an unknown error. To prevent follow-up errors, we prevent user to check out with payment method
            $extensionData->set('error', 'unknown-error');
            $this->logger->error('Error during fetching payment terms for cart. ' . $tiltaException->getMessage(), $logDefaultContext);
        }

        if ($extensionData->get('action') === 'buyer-registration-required' || $extensionData->get('action') === 'facility-request-required') {
            $formData = $this->buyerRequestFormDataRoute->getRequestFormData($initialDataForForm, $context, $customerAddress);
            $extensionData->set('addressFormData', $formData->getObject());
        }

        return $extensionData;
    }
}
