<?php

/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Tilta\TiltaPaymentSW6\Core\Util\MigrationHelper;

class Migration1742811587MakeIncorporatedDateOptional extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1_742_811_587;
    }

    public function update(Connection $connection): void
    {
        $methodName = MigrationHelper::getExecuteStatementMethod();

        $connection->{$methodName}('
            ALTER TABLE `tilta_address_data` CHANGE `incorporated_at` `incorporated_at` DATE NULL;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
