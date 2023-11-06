<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Bootstrap;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PluginConfig extends AbstractBootstrap
{
    private SystemConfigService $systemConfigService;

    /**
     * @var EntityRepository<EntityCollection<SalutationEntity>>
     */
    private EntityRepository $salutationRepository;

    public function injectServices(): void
    {
        $this->systemConfigService = $this->container->get(SystemConfigService::class); // @phpstan-ignore-line
        $this->salutationRepository = $this->container->get('salutation.repository'); // @phpstan-ignore-line
    }

    public function install(): void
    {
        // Because we can't define default values in the plugin config for entity selections,
        // we add the default values here, if they do not exist yet.
        $currentValueMale = $this->systemConfigService->get('TiltaPaymentSW6.config.salutationMale');
        $currentValueFemale = $this->systemConfigService->get('TiltaPaymentSW6.config.salutationMale');

        if (!$currentValueMale) {
            $salutationMale = $this->getSalutationId('mr');
            if ($salutationMale) {
                $this->systemConfigService->set('TiltaPaymentSW6.config.salutationMale', $salutationMale);
            }
        }

        if (!$currentValueFemale) {
            $salutationFemale = $this->getSalutationId('mrs');
            if ($salutationFemale) {
                $this->systemConfigService->set('TiltaPaymentSW6.config.salutationFemale', $salutationFemale);
            }
        }
    }

    public function update(): void
    {
    }

    public function uninstall(bool $keepUserData = false): void
    {
    }

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }

    protected function getSalutationId(string $key): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salutationKey', $key));

        $results = $this->salutationRepository->searchIds($criteria, $this->context);

        return $results->firstId();
    }
}
