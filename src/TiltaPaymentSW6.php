<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6;

use Exception;
use RuntimeException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Tilta\Sdk\HttpClient\TiltaClient;
use Tilta\TiltaPaymentSW6\Administration\TiltaAdministrationBundle;
use Tilta\TiltaPaymentSW6\Core\Bootstrap\AbstractBootstrap;
use Tilta\TiltaPaymentSW6\Core\Bootstrap\Database;
use Tilta\TiltaPaymentSW6\Core\Bootstrap\PaymentMethods;
use Tilta\TiltaPaymentSW6\Core\Bootstrap\PluginConfig;
use Tilta\TiltaPaymentSW6\Core\TiltaCoreBundle;
use Tilta\TiltaPaymentSW6\Storefront\TiltaStorefrontBundle;

class TiltaPaymentSW6 extends Plugin
{
    public function getMigrationNamespace(): string
    {
        // Migrations of child-bundles got not loaded automatically.
        // currently there are only migrations which are relevant for the whole module. so this is ok.
        return $this->getNamespace() . '\Core\Migration';
    }

    public function install(InstallContext $installContext): void
    {
        $bootstrapper = $this->getBootstrapClasses($installContext);
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->preInstall();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->install();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->postInstall();
        }
    }

    public function update(UpdateContext $updateContext): void
    {
        $bootstrapper = $this->getBootstrapClasses($updateContext);
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->preUpdate();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->update();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->postUpdate();
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $bootstrapper = $this->getBootstrapClasses($uninstallContext);
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->preUninstall();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->uninstall($uninstallContext->keepUserData());
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->postUninstall();
        }
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $bootstrapper = $this->getBootstrapClasses($deactivateContext);
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->preDeactivate();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->deactivate();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->postDeactivate();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        $bootstrapper = $this->getBootstrapClasses($activateContext);
        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->preActivate();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->activate();
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->postActivate();
        }
    }

    public function executeComposerCommands(): bool
    {
        return false; // shopware sw < 6.5 only supports this by using a feature flag. So we disable this and still using require_once at the end of the plugin-file
    }

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            new TiltaCoreBundle(),
            new TiltaAdministrationBundle(),
            new TiltaStorefrontBundle(),
        ];
    }

    /**
     * @return AbstractBootstrap[]
     */
    protected function getBootstrapClasses(InstallContext $context): array
    {
        /** @var AbstractBootstrap[] $bootstrapper */
        $bootstrapper = [
            new Database(),
            new PluginConfig(),
            new PaymentMethods(),
        ];

        /** @var EntityRepository<EntityCollection<PluginEntity>> $pluginRepository */
        $pluginRepository = $this->container->get('plugin.repository'); // @phpstan-ignore-line
        $plugins = $pluginRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('baseClass', static::class)),
            $context->getContext()
        );
        $plugin = $plugins->first();
        if (!$plugin instanceof PluginEntity) {
            throw new RuntimeException('can not load plugin entity from database.');
        }

        foreach ($bootstrapper as $bootstrap) {
            $bootstrap->setInstallContext($context);
            $bootstrap->setContainer($this->container); // @phpstan-ignore-line
            $bootstrap->injectServices();
            $bootstrap->setPlugin($plugin);
        }

        return $bootstrapper;
    }
}

if (!class_exists(TiltaClient::class)) {
    $autoloaderPath = dirname(__DIR__) . '/vendor/autoload.php';
    if (file_exists($autoloaderPath)) {
        require_once $autoloaderPath;
    } else {
        throw new Exception('Missing Tilta dependencies! Please run `composer require tilta/shopware6-payment-module` in project directory');
    }
}
