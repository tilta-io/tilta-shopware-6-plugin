<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CountryChangeIsNotAllowedException extends ShopwareHttpException
{
    public function __construct(array $addressIds)
    {
        parent::__construct('Changing the country on a existing customer-address is not allowed, if the address does have a facility.', [
            'address_ids' => $addressIds,
        ]);
    }

    public function getErrorCode(): string
    {
        return 'TILTA_COUNTRY_CHANGE_NOT_ALLOWED';
    }
}
