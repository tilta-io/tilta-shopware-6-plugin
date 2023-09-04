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

class Migration1692128696TiltaDataAddTotalAmount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1692128696;
    }

    public function update(Connection $connection): void
    {
        $methodName = MigrationHelper::getExecuteStatementMethod();

        $connection->{$methodName}('
            ALTER TABLE `tilta_address_data` 
            ADD `total_amount` INT UNSIGNED NULL AFTER `incorporated_at`, 
            ADD `valid_until` DATE NULL AFTER `total_amount`;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
