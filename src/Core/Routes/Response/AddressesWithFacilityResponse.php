<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes\Response;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class AddressesWithFacilityResponse extends StoreApiResponse
{
    /**
     * @var CustomerAddressEntity[]
     */
    private array $addressList = [];

    /**
     * @param CustomerAddressEntity[] $addressList
     */
    public function __construct(array $addressList)
    {
        parent::__construct(new ArrayStruct([
            'success' => true,
            'addressList' => $addressList,
        ]));

        $this->addressList = $addressList;
        $this->setStatusCode(self::HTTP_OK);
    }

    public function getAddressList(): array
    {
        return $this->addressList;
    }
}
