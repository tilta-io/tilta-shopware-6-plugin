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
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaDefaultPaymentHandler;

class PaymentMethods extends AbstractBootstrap
{
    final public const PAYMENT_METHODS = [
        TiltaDefaultPaymentHandler::class => [
            'handlerIdentifier' => TiltaDefaultPaymentHandler::class,
            'name' => 'Tilta Invoice',
            'description' => 'Invoice Payment allows you to delay payment for your order until a more convenient time.',
            'afterOrderEnabled' => true,
            'technicalName' => 'tilta_invoice',
            'translations' => [
                'de-DE' => [
                    'name' => 'Rechnungskauf',
                    'description' => 'Mit dem Rechnungskauf können Sie die Bezahlung Ihrer Bestellung auf einen günstigeren Zeitpunkt verschieben.',
                ],
                'en-GB' => [
                    'name' => 'Invoice Payment',
                    'description' => 'Invoice Payment allows you to delay payment for your order until a more convenient time.',
                ],
            ],
        ],
    ];

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentRepository;

    private PaymentMethodDefinition $paymentMethodDefinition;

    private Connection $connection;

    public function injectServices(): void
    {
        /** @phpstan-ignore-next-line */
        $this->paymentRepository = $this->container->get('payment_method.repository');
        /** @phpstan-ignore-next-line */
        $this->paymentMethodDefinition = $this->container->get(PaymentMethodDefinition::class);
        /** @phpstan-ignore-next-line */
        $this->connection = $this->container->get(Connection::class);
    }

    public function update(): void
    {
    }

    public function postUpdate(): void
    {
        $this->addPaymentMethods();
        $this->setTechnicalNames();
    }

    public function postInstall(): void
    {
        $this->addPaymentMethods();
        $this->setActiveFlags(false);
    }

    public function install(): void
    {
    }

    public function uninstall(bool $keepUserData = false): void
    {
        $this->setActiveFlags(false);
    }

    public function activate(): void
    {
        $this->setActiveFlags(true);
    }

    public function deactivate(): void
    {
        $this->setActiveFlags(false);
    }

    private function setActiveFlags(bool $activated): void
    {
        /** @var PaymentMethodEntity[] $paymentEntities */
        $paymentEntities = $this->paymentRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('pluginId', $this->plugin->getId())),
            $this->installContext->getContext()
        )->getElements();

        $updateData = array_map(static fn (PaymentMethodEntity $entity): array => [
            'id' => $entity->getId(),
            'active' => $activated,
        ], $paymentEntities);

        $this->paymentRepository->update(array_values($updateData), $this->installContext->getContext());
    }

    private function addPaymentMethods(): void
    {
        // add payment methods which does not exist yet
        $upsertData = [];
        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('handlerIdentifier', $paymentMethod['handlerIdentifier']));
            $criteria->setLimit(1);

            $id = $this->paymentRepository->searchIds($criteria, $this->installContext->getContext())->firstId();

            if ($id === null) {
                if (!$this->isFieldTechnicalNameAvailable()) {
                    unset($paymentMethod['technicalName']);
                }

                $paymentMethod['pluginId'] = $this->plugin->getId();
                $paymentMethod['active'] = false;
                $upsertData[] = $paymentMethod;
            }
        }

        if ($upsertData !== []) {
            $this->paymentRepository->upsert($upsertData, $this->installContext->getContext());
        }
    }

    private function setTechnicalNames(): void
    {
        if (!$this->isFieldTechnicalNameAvailable()) {
            return;
        }

        $storageNames = [
            'technicalName' => $this->getStorageName('technicalName'),
            'handlerIdentifier' => $this->getStorageName('handlerIdentifier'),
            'pluginId' => $this->getStorageName('pluginId'),
        ];

        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->connection->update(
                PaymentMethodDefinition::ENTITY_NAME,
                [
                    $storageNames['technicalName'] => $paymentMethod['technicalName'],
                ],
                [
                    $storageNames['handlerIdentifier'] => $paymentMethod['handlerIdentifier'],
                    $storageNames['pluginId'] => Uuid::fromHexToBytes($this->plugin->getId()),
                    $storageNames['technicalName'] => null,
                ]
            );
        }
    }

    private function isFieldTechnicalNameAvailable(): bool
    {
        return $this->paymentMethodDefinition->getField('technicalName') instanceof Field;
    }

    private function getStorageName(string $propertyName): string
    {
        $field = $this->paymentMethodDefinition->getField($propertyName);

        return $field instanceof StorageAware ? $field->getStorageName() : $propertyName;
    }
}
