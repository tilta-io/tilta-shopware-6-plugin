<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Tilta\TiltaPaymentSW6\Core\Util\MigrationHelper;

class Migration1691162896TiltaCustomerAddressData extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1691162896;
    }

    public function update(Connection $connection): void
    {
        $methodName = MigrationHelper::getExecuteStatementMethod();

        $connection->{$methodName}('
            CREATE TABLE `tilta_address_data` (
              `customer_address_id` binary(16) NOT NULL,
              `buyer_external_id` varchar(255) DEFAULT NULL,
              `legal_form` varchar(50) NOT NULL,
              `incorporated_at` date NOT NULL,
              PRIMARY KEY(`customer_address_id`),
              KEY `FK_CUSTOMER_ADDRESS_TILTA_DATA` (`customer_address_id`),
              CONSTRAINT `FK_CUSTOMER_ADDRESS_TILTA_DATA` FOREIGN KEY (`customer_address_id`) REFERENCES `customer_address` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
