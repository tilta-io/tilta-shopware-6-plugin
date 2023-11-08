<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\Sdk\Exception\GatewayException\Facility\FacilityExceededException;
use Tilta\Sdk\Exception\GatewayException\Facility\NoActiveFacilityFoundException;
use Tilta\Sdk\Exception\GatewayException\NotFoundException\BuyerNotFoundException;
use Tilta\Sdk\Model\Amount;
use Tilta\Sdk\Model\Request\PaymentTerm\GetPaymentTermsRequestModel;
use Tilta\Sdk\Model\Response\PaymentTerm\GetPaymentTermsResponseModel;
use Tilta\Sdk\Service\Request\PaymentTerm\GetPaymentTermsRequest;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Util\AmountHelper;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;

class PaymentTermsService
{
    private GetPaymentTermsRequest $paymentTermsRequest;

    private ConfigService $configService;

    private CustomerAddressHelper $customerAddressHelper;

    private FacilityService $facilityService;

    private EntityHelper $entityHelper;

    public function __construct(
        GetPaymentTermsRequest $paymentTermsRequest,
        ConfigService $configService,
        CustomerAddressHelper $customerAddressHelper,
        FacilityService $facilityService,
        EntityHelper $entityHelper
    ) {
        $this->paymentTermsRequest = $paymentTermsRequest;
        $this->configService = $configService;
        $this->customerAddressHelper = $customerAddressHelper;
        $this->facilityService = $facilityService;
        $this->entityHelper = $entityHelper;
    }

    /**
     * @throws BuyerNotFoundException
     * @throws NoActiveFacilityFoundException
     * @throws FacilityExceededException
     */
    public function getPaymentTermsForCart(SalesChannelContext $salesChannelContext, Cart $cart): ?GetPaymentTermsResponseModel
    {
        $customerAddress = $salesChannelContext->getCustomer() instanceof CustomerEntity ? $salesChannelContext->getCustomer()->getActiveBillingAddress() : null;
        if (!$customerAddress instanceof CustomerAddressEntity) {
            return null;
        }

        return $this->getPaymentTermsForCustomerAddress(
            $customerAddress,
            $cart->getPrice(),
            $salesChannelContext->getCurrency()->getIsoCode()
        );
    }

    /**
     * @throws BuyerNotFoundException
     * @throws NoActiveFacilityFoundException
     * @throws FacilityExceededException
     */
    public function getPaymentTermsForOrder(OrderEntity $orderEntity): ?GetPaymentTermsResponseModel
    {
        $customerAddress = $this->customerAddressHelper->getCustomerAddressForOrder($orderEntity);
        if (!$customerAddress instanceof CustomerAddressEntity) {
            return null;
        }

        return $this->getPaymentTermsForCustomerAddress(
            $customerAddress,
            $orderEntity->getPrice(),
            $this->entityHelper->getCurrencyCode($orderEntity)
        );
    }

    /**
     * @throws BuyerNotFoundException
     * @throws NoActiveFacilityFoundException
     * @throws FacilityExceededException
     */
    public function getPaymentTermsForCustomerAddress(CustomerAddressEntity $customerAddress, CartPrice $price, string $currencyCode): ?GetPaymentTermsResponseModel
    {
        $tiltaData = $customerAddress->getExtension(CustomerAddressEntityExtension::TILTA_DATA);
        if (!$tiltaData instanceof TiltaCustomerAddressDataEntity) {
            return null;
        }

        if ($tiltaData->getBuyerExternalId() === null || $tiltaData->getBuyerExternalId() === '') {
            return null;
        }

        $requestModel = (new GetPaymentTermsRequestModel())
            ->setMerchantExternalId($this->configService->getMerchantExternalId())
            ->setBuyerExternalId($tiltaData->getBuyerExternalId())
            ->setAmount(
                (new Amount())
                    ->setNet(AmountHelper::toSdk($price->getNetPrice()))
                    ->setGross(AmountHelper::toSdk($price->getTotalPrice()))
                    ->setTax(AmountHelper::toSdk($price->getCalculatedTaxes()->getAmount()))
                    ->setCurrency($currencyCode)
            );

        $paymentTerms = $this->paymentTermsRequest->execute($requestModel);

        $this->facilityService->updateFacilityOnCustomerAddress($customerAddress, $paymentTerms->getFacility());

        return $paymentTerms;
    }
}
