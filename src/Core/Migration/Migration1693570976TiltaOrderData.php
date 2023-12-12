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

class Migration1693570976TiltaOrderData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1693570976;
    }

    public function update(Connection $connection): void
    {
        $methodName = MigrationHelper::getExecuteStatementMethod();

        $connection->{$methodName}('
            CREATE TABLE `tilta_order_data` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL, 
                `order_version_id` BINARY(16) NOT NULL,
                `order_external_id` VARCHAR(255) NOT NULL, 
                `buyer_external_id` VARCHAR(255) NOT NULL, 
                `merchant_external_id` VARCHAR(255) NOT NULL, 
                `invoice_number` VARCHAR(255) NULL, 
                `invoice_external_id` VARCHAR(255) NULL, 
                `status` VARCHAR(255) NOT NULL,
                PRIMARY KEY(`id`,`version_id`,`order_id`),
                CONSTRAINT `FK_TILTA_ORDER_ORDER_ENTITY` FOREIGN KEY (`order_id`,`order_version_id`) REFERENCES `order` (`id`, `version_id`) ON UPDATE CASCADE ON DELETE CASCADE
            ) ENGINE = InnoDB;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
