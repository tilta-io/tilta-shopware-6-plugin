<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraint;
use Tilta\TiltaPaymentSW6\Core\Exception\CountryChangeIsNotAllowedException;
use Tilta\TiltaPaymentSW6\Core\Util\CustomerAddressHelper;

class CountryChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepository<EntityCollection<CustomerAddressEntity>>
     */
    private EntityRepository $customerAddressRepository;

    private CustomerAddressHelper $customerAddressHelper;

    /**
     * @param EntityRepository<EntityCollection<CustomerAddressEntity>> $customerAddressRepository
     */
    public function __construct(
        EntityRepository $customerAddressRepository,
        CustomerAddressHelper $customerAddressHelper
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerAddressHelper = $customerAddressHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.address.update' => 'validationBuilt',
            PreWriteValidationEvent::class => 'validateForCountryChange',
        ];
    }

    public function validationBuilt(BuildValidationEvent $event): void
    {
        $addressId = $event->getData()->getAlnum('id');
        if ($addressId && !$this->customerAddressHelper->canCountryChanged($event->getContext(), $addressId)) {
            /** @var CustomerAddressEntity|null $existingAddress */
            $existingAddress = $this->customerAddressRepository->search(new Criteria([$addressId]), $event->getContext())->first();
            if (!$existingAddress instanceof CustomerAddressEntity) {
                return; // does not make sense - just to be safe.
            }

            $properties = $event->getDefinition()->getProperties();
            /** @var Constraint[] $list */
            $list = $properties['countryId'] ?? [];
            foreach ($list as $item) {
                if ($item instanceof EntityExists) {
                    $item->getCriteria()->addFilter(new EqualsFilter('id', $existingAddress->getCountryId()));
                }
            }
        }
    }

    public function validateForCountryChange(PreWriteValidationEvent $event): void
    {
        $commands = $event->getCommands();

        $failedAddressIds = [];
        foreach ($commands as $command) {
            $payload = $command->getPayload();
            if ($command->getEntityName() !== CustomerAddressDefinition::ENTITY_NAME || !isset($payload['country_id']) || !is_string($payload['country_id'])) {
                continue;
            }

            $addressId = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
            if (!$this->customerAddressHelper->canCountryChanged($event->getContext(), $addressId, Uuid::fromBytesToHex($payload['country_id']))) {
                $failedAddressIds[] = $addressId;
            }
        }

        if ($failedAddressIds !== []) {
            $event->getExceptions()->add(new CountryChangeIsNotAllowedException($failedAddressIds));
        }
    }
}
