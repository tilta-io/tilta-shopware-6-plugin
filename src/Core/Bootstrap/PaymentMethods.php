<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Bootstrap;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Tilta\TiltaPaymentSW6\Core\PaymentHandler\TiltaDefaultPaymentHandler;

class PaymentMethods extends AbstractBootstrap
{
    /**
     * @var array<string, array<string, (class-string<TiltaDefaultPaymentHandler> | bool | array<string, array<string, string>> | string)>>
     */
    public const PAYMENT_METHODS = [
        TiltaDefaultPaymentHandler::class => [
            'handlerIdentifier' => TiltaDefaultPaymentHandler::class,
            'name' => 'Tilta Invoice',
            'description' => 'Invoice Payment allows you to delay payment for your order until a more convenient time.',
            'afterOrderEnabled' => true,
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
     * @var EntityRepository<EntityCollection<PaymentMethodEntity>>
     */
    private object $paymentRepository;

    public function injectServices(): void
    {
        /** @phpstan-ignore-next-line */
        $this->paymentRepository = $this->container->get('payment_method.repository');
    }

    public function update(): void
    {
        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->upsertPaymentMethod($paymentMethod);
        }

        // Keep active flags as they are
    }

    public function install(): void
    {
        foreach (self::PAYMENT_METHODS as $paymentMethod) {
            $this->upsertPaymentMethod($paymentMethod);
        }

        $this->setActiveFlags(false);
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

    protected function upsertPaymentMethod(array $paymentMethod): void
    {
        $paymentSearchResult = $this->paymentRepository->search(
            (
                (new Criteria())
                    ->addFilter(new EqualsFilter('handlerIdentifier', $paymentMethod['handlerIdentifier']))
                    ->setLimit(1)
            ),
            $this->defaultContext
        );

        /** @var PaymentMethodEntity|null $paymentEntity */
        $paymentEntity = $paymentSearchResult->first();
        if ($paymentEntity instanceof PaymentMethodEntity) {
            $paymentMethod['id'] = $paymentEntity->getId();
        }

        $paymentMethod['pluginId'] = $this->plugin->getId();
        $this->paymentRepository->upsert([$paymentMethod], $this->defaultContext);
    }

    protected function setActiveFlags(bool $activated): void
    {
        /** @var PaymentMethodEntity[] $paymentEntities */
        $paymentEntities = $this->paymentRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('pluginId', $this->plugin->getId())),
            $this->defaultContext
        )->getElements();

        $updateData = array_map(static fn (PaymentMethodEntity $entity): array => [
            'id' => $entity->getId(),
            'active' => $activated,
        ], $paymentEntities);

        $this->paymentRepository->update(array_values($updateData), $this->defaultContext);
    }
}
