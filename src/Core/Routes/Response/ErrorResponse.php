<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes\Response;

use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @method ArrayStruct getObject()
 */
class ErrorResponse extends StoreApiResponse
{
    public function __construct(array $errors, int $statusCode)
    {
        parent::__construct(new ArrayStruct([
            'errors' => $errors,
        ]));
        $this->setStatusCode($statusCode);
    }

    public function getErrors(): array
    {
        $errors = $this->getObject()->get('errors');

        return is_array($errors) ? $errors : [];
    }
}
