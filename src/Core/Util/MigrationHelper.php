<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Util;

use Doctrine\DBAL\Connection;
use ReflectionClass;

class MigrationHelper
{
    public static function getExecuteStatementMethod(): string
    {
        return (new ReflectionClass(Connection::class))
            ->hasMethod('executeStatement') ? 'executeStatement' : 'executeQuery';
    }
}
