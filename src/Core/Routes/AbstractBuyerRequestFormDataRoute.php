<?php

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\TiltaPaymentSW6\Core\Routes\Response\BuyerRequestFormDataResponse;

abstract class AbstractBuyerRequestFormDataRoute
{
    abstract public function getDecorated(): AbstractBuyerRequestFormDataRoute;

    abstract public function getRequestFormData(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerAddressEntity $customerAddress): BuyerRequestFormDataResponse;
}
