<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Tests\Functional\Core\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Tilta\Sdk\Model\Response\Order\GetPaymentTermsResponseModel;
use Tilta\Sdk\Service\Request\Order\GetPaymentTermsRequest;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Service\ConfigService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Service\PaymentTermsService;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;
use Tilta\TiltaPaymentSW6\Tests\TiltaTestBehavior;

class PaymentTermsServiceTest extends TestCase
{
    use TiltaTestBehavior;

    public function testNoCustomer()
    {
        $context = $this->createSalesChannelContext();
        $service = $this->getContainer()->get(PaymentTermsService::class);

        $this->addProductToCart($this->getRandomProduct($context)->getId(), $context);
        $this->addProductToCart($this->getRandomProduct($context)->getId(), $context);
        $terms = $service->getPaymentTermsForCart($context, $this->getContainer()->get(CartService::class)->getCart($context->getToken(), $context));
        static::assertNull($terms, 'no terms should be available, because customer is not logged in.');

        $orderEntity = new OrderEntity();
        $terms = $service->getPaymentTermsForOrder($orderEntity);
        static::assertNull($terms, 'no terms should be available, because order-customer is not set on order');

        $orderEntity = new OrderEntity();
        $orderEntity->setBillingAddress(new OrderAddressEntity());
        $terms = $service->getPaymentTermsForOrder($orderEntity);
        static::assertNull($terms, 'no terms should be available, because no customer address can be found with billing address');
    }

    public function testNoBuyerExternalId()
    {
        $service = $this->getContainer()->get(PaymentTermsService::class);
        $price = new CartPrice(100.00, 100.00, 100.00, new CalculatedTaxCollection([]), new TaxRuleCollection([]), '');

        $terms = $service->getPaymentTermsForCustomerAddress(new CustomerAddressEntity(), $price, 'EUR');
        static::assertNull($terms, 'no terms should be available, because no Tilta data has been set');

        $address = new CustomerAddressEntity();
        $address->addExtension(CustomerAddressEntityExtension::TILTA_DATA, new TiltaCustomerAddressDataEntity());
        $terms = $service->getPaymentTermsForCustomerAddress($address, $price, 'EUR');
        static::assertNull($terms, 'no terms should be available, because no buyer-external-id has been set');

        $address = new CustomerAddressEntity();
        $tiltaData = (new TiltaCustomerAddressDataEntity())->assign([
            TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => null,
        ]);
        $address->addExtension(CustomerAddressEntityExtension::TILTA_DATA, $tiltaData);
        $terms = $service->getPaymentTermsForCustomerAddress($address, $price, 'EUR');
        static::assertNull($terms, 'no terms should be available, because buyer-external-id is empty');

        $address = new CustomerAddressEntity();
        $tiltaData = (new TiltaCustomerAddressDataEntity())->assign([
            TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => '',
        ]);
        $address->addExtension(CustomerAddressEntityExtension::TILTA_DATA, $tiltaData);
        $terms = $service->getPaymentTermsForCustomerAddress($address, $price, 'EUR');
        static::assertNull($terms, 'no terms should be available, because buyer-external-id is empty');
    }

    public function testGetPaymentTermsForCustomerAddress()
    {
        $service = new PaymentTermsService(
            $paymentTermsRequest = $this->createMock(GetPaymentTermsRequest::class),
            $this->createMock(ConfigService::class),
            $this->createMock(CustomerAddressHelper::class),
            $facilityService = $this->createMock(FacilityService::class),
            $this->getContainer()->get(EntityHelper::class)
        );
        $price = new CartPrice(100.00, 100.00, 100.00, new CalculatedTaxCollection([]), new TaxRuleCollection([]), '');

        $paymentTermsRequest->expects($this->once())->method('execute')->willReturn(new GetPaymentTermsResponseModel([
            'facility' => [
                'status' => 'new',
                'expires_at' => (new \DateTime())->modify('+1 day')->getTimestamp(),
                'currency' => 'EUR',
                'total_amount' => 100000,
                'available_amount' => 100000,
                'used_amount' => 0,
            ],
            'payment_terms' => [],
        ]));

        // should be always called, if a facility got returned.
        $facilityService->expects($this->once())->method('updateFacilityOnCustomerAddress');

        $address = new CustomerAddressEntity();
        $tiltaData = (new TiltaCustomerAddressDataEntity())->assign([
            TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => 'buyer-external-id',
        ]);
        $address->addExtension(CustomerAddressEntityExtension::TILTA_DATA, $tiltaData);
        $terms = $service->getPaymentTermsForCustomerAddress($address, $price, 'EUR');
        static::assertInstanceOf(GetPaymentTermsResponseModel::class, $terms);
    }
}
