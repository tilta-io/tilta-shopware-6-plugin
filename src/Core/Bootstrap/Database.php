<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Bootstrap;

use Doctrine\DBAL\Connection;
use Exception;
use Tilta\TiltaPaymentSW6\Core\Util\MigrationHelper;

class Database extends AbstractBootstrap
{
    protected Connection $connection;

    public function injectServices(): void
    {
        $this->connection = $this->container->get(Connection::class); // @phpstan-ignore-line
    }

    public function install(): void
    {
    }

    public function update(): void
    {
    }

    /**
     * @throws Exception
     */
    public function uninstall(bool $keepUserData = false): void
    {
        if ($keepUserData) {
            return;
        }

        $method = MigrationHelper::getExecuteStatementMethod();
        $this->connection->{$method === 'executeStatement' ? $method : 'exec'}('DROP TABLE IF EXISTS tilta_address_data;');
        $this->connection->{$method === 'executeStatement' ? $method : 'exec'}('DROP TABLE IF EXISTS tilta_order_data;');
    }

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }
}
