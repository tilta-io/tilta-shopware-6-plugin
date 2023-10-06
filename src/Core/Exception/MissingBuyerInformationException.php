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

class MissingBuyerInformationException extends ShopwareHttpException
{
    // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be removed for 6.5 (or >=6.4.15)
    private array $_parameters = [];

    public function __construct(array $messages)
    {
        $parameters = [
            'errors' => $messages,
        ];
        // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be removed for 6.5 (or >=6.4.15)
        if (property_exists($this, 'parameters')) {
            parent::__construct('There are missing fields', $parameters);
        } else {
            parent::__construct('There are missing fields');
            $this->_parameters = $parameters;
        }
    }

    public function getErrorMessages(): array
    {
        // deprecated: ShopwareHttpException::parameters has been added in 6.4.15 - can be adjusted for 6.5 (or >=6.4.15)
        $errors = method_exists($this, 'getParameter') ? $this->getParameter('errors') : $this->_parameters['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }

    public function getErrorCode(): string
    {
        return 'TILTA_BUYER_MISSING_DATA';
    }
}
