<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Bootstrap;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AbstractBootstrap
{
    protected InstallContext $installContext;

    protected Context $defaultContext;

    protected PluginEntity $plugin;

    protected ContainerInterface $container;

    final public function __construct()
    {
        $this->defaultContext = Context::createDefaultContext();
    }

    abstract public function install(): void;

    abstract public function update(): void;

    abstract public function uninstall(bool $keepUserData = false): void;

    abstract public function activate(): void;

    abstract public function deactivate(): void;

    final public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function injectServices(): void
    {
    }

    final public function setInstallContext(InstallContext $installContext): void
    {
        $this->installContext = $installContext;
    }

    final public function setPlugin(PluginEntity $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function preInstall(): void
    {
    }

    public function preUpdate(): void
    {
    }

    public function preUninstall(bool $keepUserData = false): void
    {
    }

    public function preActivate(): void
    {
    }

    public function preDeactivate(): void
    {
    }

    public function postActivate(): void
    {
    }

    public function postDeactivate(): void
    {
    }

    public function postUninstall(): void
    {
    }

    public function postUpdate(): void
    {
    }

    public function postInstall(): void
    {
    }

    final protected function getPluginPath(): string
    {
        /** @var string $rootDir */
        $rootDir = $this->container->getParameter('kernel.root_dir');

        return $rootDir . DIRECTORY_SEPARATOR . $this->plugin->getPath();
    }
}
