<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Subscriber;

use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;

class CustomerAddressSubscriber implements EventSubscriberInterface
{
    private EntityRepository $customerAddressRepository;

    private BuyerService $buyerService;

    private LoggerInterface $logger;

    private array $fieldsToWatch = [
        CustomerAddressDefinition::ENTITY_NAME => [
            'countryId',
            'salutationId',
            'firstName',
            'lastName',
            'zipcode',
            'company',
            'street',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
        ],
        CustomerDefinition::ENTITY_NAME => [
            'email',
            'birthday',
        ],
    ];

    public function __construct(
        EntityRepository $customerAddressRepository,
        BuyerService $buyerService,
        LoggerInterface $logger,
        array $additionalCustomerAddressFieldsToWatch = [],
        array $additionalCustomerFieldsToWatch = []
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->buyerService = $buyerService;
        $this->logger = $logger;
        $this->fieldsToWatch[CustomerAddressDefinition::ENTITY_NAME] = array_merge($this->fieldsToWatch[CustomerAddressDefinition::ENTITY_NAME], $additionalCustomerAddressFieldsToWatch);
        $this->fieldsToWatch[CustomerDefinition::ENTITY_NAME] = array_merge($this->fieldsToWatch[CustomerDefinition::ENTITY_NAME], $additionalCustomerFieldsToWatch);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerAddressDefinition::ENTITY_NAME . '.written' => 'onWrittenCustomerAddress',
            CustomerDefinition::ENTITY_NAME . '.written' => 'onWrittenCustomer',
        ];
    }

    public function onWrittenCustomer(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== CustomerDefinition::ENTITY_NAME) {
            return;
        }

        $ids = $this->filterChangedIds($event);
        if ($ids === []) {
            return;
        }

        $criteria = (new Criteria())
            ->addAssociation('customer')
            ->addFilter(new EqualsAnyFilter('customerId', $ids))
            ->addFilter(new NotFilter(
                NotFilter::CONNECTION_AND,
                [new EqualsFilter('tiltaData.buyerExternalId', null)]
            ));

        /** @var EntityCollection<CustomerAddressEntity> $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $event->getContext());

        $this->updateAddressList($addresses->getElements());
    }

    public function onWrittenCustomerAddress(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== CustomerAddressDefinition::ENTITY_NAME) {
            return;
        }

        $ids = $this->filterChangedIds($event);
        if ($ids === []) {
            return;
        }

        $criteria = new Criteria($ids);
        $criteria->addAssociation('customer');

        /** @var EntityCollection<CustomerAddressEntity> $addresses */
        $addresses = $this->customerAddressRepository->search($criteria, $event->getContext());

        $this->updateAddressList($addresses->getElements());
    }

    /**
     * @param CustomerAddressEntity[] $elements
     */
    private function updateAddressList(array $elements): void
    {
        foreach ($elements as $customerAddress) {
            $tiltaData = $customerAddress->getExtension(CustomerAddressEntityExtension::TILTA_DATA);
            if (!$tiltaData instanceof TiltaCustomerAddressDataEntity || $tiltaData->getBuyerExternalId() === null) {
                // has been already filtered, but just to be safe.
                continue;
            }

            try {
                $this->buyerService->updateBuyer($customerAddress);
            } catch (Exception $exception) {
                $additionalData = [];
                if ($exception instanceof MissingBuyerInformationException) {
                    $additionalData['error_messages'] = $exception->getErrorMessages();
                }

                $this->logger->error('Can not update buyer: ' . $exception->getMessage(), array_merge([
                    'customer_id' => $customerAddress->getCustomerId(),
                    'customer_address_id' => $customerAddress->getId(),
                    'tilta_buyer_external_id' => $tiltaData->getBuyerExternalId(),
                ], $additionalData));
            }
        }
    }

    private function filterChangedIds(EntityWrittenEvent $event): array
    {
        $ids = [];
        foreach ($event->getWriteResults() as $result) {
            if (array_intersect_key(array_flip($this->fieldsToWatch[$event->getEntityName()] ?? []), $result->getPayload()) !== []) {
                $ids[] = $result->getPrimaryKey();
            }
        }

        return $ids;
    }
}
