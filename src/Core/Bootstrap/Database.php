<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $this->connection->{$method === 'executeStatement' ? $method : 'exec'}('SET FOREIGN_KEY_CHECKS=0;');

        $this->connection->{$method === 'executeStatement' ? $method : 'exec'}('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }
}
