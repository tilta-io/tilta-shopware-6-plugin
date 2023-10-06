<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Tests;

use DateTime;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Integration\Traits\TestShortHands;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaDefaultPaymentHandler;

trait TiltaTestBehavior
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;
    use TestShortHands;

    protected function getTiltaPaymentMethod(): PaymentMethodEntity
    {
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $paymentMethodCriteria = new Criteria();
        $paymentMethodCriteria->addFilter(new EqualsFilter('handlerIdentifier', TiltaDefaultPaymentHandler::class));
        $paymentMethodCriteria->setLimit(1);
        $tiltaPaymentMethod = $paymentMethodRepository->search($paymentMethodCriteria, Context::createDefaultContext())->first();
        static::assertInstanceOf(PaymentMethodEntity::class, $tiltaPaymentMethod);

        return $tiltaPaymentMethod;
    }

    protected function createSalesChannelContextWithTiltaBuyer(array $salesChannelContextOverrides = []): SalesChannelContext
    {
        $customer = $this->createCustomer();
        /** @var EntityRepository $addressRepo */
        $addressRepo = $this->getContainer()->get('customer_address.repository');
        $addressRepo->upsert([[
            'id' => $customer->getDefaultBillingAddressId(),
            'company' => 'test-company',
            CustomerAddressEntityExtension::TILTA_DATA => [
                TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => 'buyer-external-id',
                TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => 'GMBH',
                TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => new DateTime(),
            ],
        ]], Context::createDefaultContext());

        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getAvailableShippingMethod()->getId();
        $countryId = $this->getValidCountryId();
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $salesChannelContext = $this->createContext(
            array_merge([
                'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'name' => 'store front',
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $snippetSetId,
                'currencyId' => Defaults::CURRENCY,
                'currencyVersionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $paymentMethodId,
                'paymentMethodVersionId' => Defaults::LIVE_VERSION,
                'shippingMethodId' => $shippingMethodId,
                'shippingMethodVersionId' => Defaults::LIVE_VERSION,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $countryId,
                'countryVersionId' => Defaults::LIVE_VERSION,
                'currencies' => [[
                    'id' => Defaults::CURRENCY,
                ]],
                'languages' => [[
                    'id' => Defaults::LANGUAGE_SYSTEM,
                ]],
                'paymentMethods' => [[
                    'id' => $paymentMethodId,
                ]],
                'shippingMethods' => [[
                    'id' => $shippingMethodId,
                ]],
                'countries' => [[
                    'id' => $countryId,
                ]],
                'domains' => [
                    [
                        'url' => 'http://test.com/' . Uuid::randomHex(),
                        'currencyId' => Defaults::CURRENCY,
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'snippetSetId' => $snippetSetId,
                    ],
                ],
            ], $salesChannelContextOverrides),
            [
                SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
            ]
        );

        static::assertNotNull($salesChannelContext->getCustomer());

        $billingAddress = $salesChannelContext->getCustomer()->getActiveBillingAddress();
        static::assertEquals('test-company', $billingAddress->getCompany());
        static::assertNotNull($billingAddress->getExtension(CustomerAddressEntityExtension::TILTA_DATA));

        return $salesChannelContext;
    }

    protected function createRandomOrder(SalesChannelContext $context = null): OrderEntity
    {
        $context = $context ?? $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);

        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociations(['addresses', 'orderCustomer', 'billingAddress']);
        $orderEntity = $orderRepository->search($orderCriteria, $context->getContext())->first();
        static::assertInstanceOf(OrderEntity::class, $orderEntity);

        return $orderEntity;
    }

    protected function setDummyConfiguration()
    {
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('TiltaPaymentSW6.config.sandbox', true);
        $systemConfigService->set('TiltaPaymentSW6.config.sandboxApiAuthToken', 'test');
        $systemConfigService->set('TiltaPaymentSW6.config.sandboxApiMerchantExternalId', 'test');
    }

    protected function createRandomTiltaOrder(): OrderEntity
    {
        $this->setDummyConfiguration(); // this is required, because without the default configuration, the payment method will be not available.

        $tiltaPaymentMethod = $this->getTiltaPaymentMethod();
        $salesChannelContext = $this->createSalesChannelContextWithTiltaBuyer([
            'paymentMethods' => [
                [
                    'id' => $tiltaPaymentMethod->getId(),
                ],
                [
                    'id' => $this->getValidPaymentMethodId(),
                ], // we should not remove the default valid payment method
            ],
            SalesChannelContextService::PAYMENT_METHOD_ID => $tiltaPaymentMethod->getId(),
        ]);

        return $this->createRandomOrder($salesChannelContext);
    }

    protected function getPageLoader(): CheckoutConfirmPageLoader
    {
        return static::getContainer()->get(CheckoutConfirmPageLoader::class);
    }
}
