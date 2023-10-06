<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\TiltaPaymentSW6\Core\Routes\Response\BuyerRequestFormDataResponse;

abstract class AbstractBuyerRequestFormDataRoute
{
    abstract public function getDecorated(): self;

    abstract public function getRequestFormData(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerAddressEntity $customerAddress): BuyerRequestFormDataResponse;
}
