<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Service;

use DateTime;
use DateTimeInterface;
use RuntimeException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tilta\Sdk\Exception\GatewayException\NotFoundException\BuyerNotFoundException;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Address;
use Tilta\Sdk\Model\Buyer;
use Tilta\Sdk\Model\BuyerRepresentative;
use Tilta\Sdk\Model\Request\Buyer\CreateBuyerRequestModel;
use Tilta\Sdk\Model\Request\Buyer\GetBuyerDetailsRequestModel;
use Tilta\Sdk\Model\Request\Buyer\UpdateBuyerRequestModel;
use Tilta\Sdk\Service\Request\Buyer\CreateBuyerRequest;
use Tilta\Sdk\Service\Request\Buyer\GetBuyerDetailsRequest;
use Tilta\Sdk\Service\Request\Buyer\UpdateBuyerRequest;
use Tilta\Sdk\Util\AddressHelper;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;

class BuyerService
{
    private EntityRepository $customerAddressRepository;

    private EntityRepository $tiltaAddressDataRepository;

    private TranslatorInterface $translator;

    private ContainerInterface $container;

    private SystemConfigService $configService;

    public function __construct(
        EntityRepository $customerAddressRepository,
        EntityRepository $tiltaAddressDataRepository,
        TranslatorInterface $translator,
        ContainerInterface $container,
        SystemConfigService $configService
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->tiltaAddressDataRepository = $tiltaAddressDataRepository;
        $this->translator = $translator;
        $this->container = $container;
        $this->configService = $configService;
    }

    public static function generateBuyerExternalId(CustomerAddressEntity $address): string
    {
        $externalId = null;
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);
        if ($tiltaData instanceof TiltaCustomerAddressDataEntity) {
            $externalId = $tiltaData->getBuyerExternalId();
        }

        // added for PHPStan: customer is always loaded
        /** @var CustomerEntity $customer */
        $customer = $address->getCustomer();

        return $externalId !== null && $externalId !== '' ? $externalId : ((!empty($customer->getCustomerNumber()) ? $customer->getCustomerNumber() : $customer->getId()) . '-' . $address->getId());
    }

    public function updateCustomerAddressData(CustomerAddressEntity $addressEntity, array $data): void
    {
        $context = Context::createDefaultContext();
        $this->customerAddressRepository->upsert([
            [
                'id' => $addressEntity->getId(),
                'salutationId' => $data['salutationId'],
                'phoneNumber' => $data['phoneNumber'],
            ],
        ], $context);

        if (is_string($data['incorporatedAt']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['incorporatedAt'])) {
            $incorporatedAt = DateTime::createFromFormat('Y-m-d', $data['incorporatedAt']);
        } elseif ($data['incorporatedAt'] instanceof DateTimeInterface) {
            $incorporatedAt = $data['incorporatedAt'];
        } else {
            throw new RuntimeException('incorporatedAt have to a datetime or a date formatted as Y-m-d');
        }

        $this->tiltaAddressDataRepository->upsert([
            [
                TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $addressEntity->getId(),
                TiltaCustomerAddressDataEntity::FIELD_INCORPORATED_AT => $incorporatedAt,
                TiltaCustomerAddressDataEntity::FIELD_LEGAL_FORM => $data['legalForm'],
            ],
        ], $context);
    }

    /**
     * @throws TiltaException
     */
    public function createBuyerIfNotExist(CustomerAddressEntity $address): Buyer
    {
        $this->validateAdditionalData($address);

        $buyerExternalId = self::generateBuyerExternalId($address);

        try {
            /** @var GetBuyerDetailsRequest $buyerRequest */
            $buyerRequest = $this->container->get(GetBuyerDetailsRequest::class);

            return $buyerRequest->execute(new GetBuyerDetailsRequestModel($buyerExternalId));
        } catch (BuyerNotFoundException $buyerNotFoundException) {
            $buyerRequestModel = $this->createCreateBuyerRequestModel($address);

            /** @var CreateBuyerRequest $createBuyerRequest */
            $createBuyerRequest = $this->container->get(CreateBuyerRequest::class);

            $createBuyerRequest->execute($buyerRequestModel);

            $this->tiltaAddressDataRepository->upsert([
                [
                    TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $address->getId(),
                    TiltaCustomerAddressDataEntity::FIELD_BUYER_EXTERNAL_ID => $buyerExternalId,
                ],
            ], Context::createDefaultContext());

            return $buyerRequestModel;
        }
    }

    public function updateBuyer(CustomerAddressEntity $address): void
    {
        $buyerRequestModel = $this->createUpdateBuyerRequestModel($address);

        /** @var UpdateBuyerRequest $requestService */
        $requestService = $this->container->get(UpdateBuyerRequest::class);

        $requestService->execute($buyerRequestModel);
    }

    /**
     * @throws MissingBuyerInformationException
     */
    public function createCreateBuyerRequestModel(CustomerAddressEntity $address): CreateBuyerRequestModel
    {
        return $this->getRequestModel(
            CreateBuyerRequestModel::class,
            $address,
            'tilta_create_buyer_request_built'
        );
    }

    /**
     * @throws MissingBuyerInformationException
     */
    public function createUpdateBuyerRequestModel(CustomerAddressEntity $address): UpdateBuyerRequestModel
    {
        return $this->getRequestModel(
            UpdateBuyerRequestModel::class,
            $address,
            'tilta_update_buyer_request_built'
        );
    }

    public function validateAdditionalData(CustomerAddressEntity $address): void
    {
        $errors = [];

        if ((string) $address->getSalutationId() === '') {
            $errors[] = $this->translator->trans('tilta.messages.invalid-salutation');
        }

        if ((string) $address->getPhoneNumber() === '') {
            $errors[] = $this->translator->trans('tilta.messages.invalid-phone');
        }

        if ((string) $address->getCompany() === '') {
            $errors[] = $this->translator->trans('tilta.messages.invalid-company');
        }

        /** @var TiltaCustomerAddressDataEntity|null $tiltaData */
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        if (!$tiltaData instanceof TiltaCustomerAddressDataEntity || !$tiltaData->getIncorporatedAt() instanceof DateTimeInterface) {
            $errors[] = $this->translator->trans('tilta.messages.invalid-incorporate-at');
        }

        if (!$tiltaData instanceof TiltaCustomerAddressDataEntity || (string) $tiltaData->getLegalForm() === '') {
            $errors[] = $this->translator->trans('tilta.messages.invalid-legal-form');
        }

        if ($errors !== []) {
            throw new MissingBuyerInformationException($errors);
        }
    }

    private function getMappedSalutationFromAddress(CustomerAddressEntity $addressEntity): string
    {
        $salutationId = $addressEntity->getSalutationId();
        if ($salutationId === $this->configService->get('TiltaPaymentSW6.config.salutationMale')) {
            return 'MR';
        }

        if ($salutationId === $this->configService->get('TiltaPaymentSW6.config.salutationFemale')) {
            return 'MS';
        }

        $fallbackValue = $this->configService->get('TiltaPaymentSW6.config.salutationFallback');

        switch ($fallbackValue) {
            case 'f':
                return 'MS';
            case 'm':
            default:
                return 'MR';
        }
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @return T
     * @throws MissingBuyerInformationException
     */
    private function getRequestModel(string $class, CustomerAddressEntity $address, string $eventName)
    {
        $this->validateAdditionalData($address);

        /** @var TiltaCustomerAddressDataEntity $tiltaData */ // is never null, cause validated in `validateAdditionalData`
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        // added for PHPStan: customer is always loaded
        /** @var CustomerEntity $customer */
        $customer = $address->getCustomer();

        $buyerExternalId = self::generateBuyerExternalId($address);
        switch ($class) {
            case CreateBuyerRequestModel::class:
                $requestModel = (new CreateBuyerRequestModel())
                    ->setExternalId($buyerExternalId);
                break;
            case UpdateBuyerRequestModel::class:
                $requestModel = new UpdateBuyerRequestModel($buyerExternalId);
                break;
            default:
                throw new RuntimeException('invalid request class');
        }

        $requestModel
            ->setLegalName($address->getCompany())
            ->setTradingName($address->getCompany())
            ->setLegalForm($tiltaData->getLegalForm())
            ->setRegisteredAt($customer->getCreatedAt() ?? new DateTime()) // should be always set.
            ->setIncorporatedAt($tiltaData->getIncorporatedAt())
            ->setBusinessAddress(
                (new Address())
                    ->setAdditional($address->getAdditionalAddressLine1()) // TODO merge with line 2?
                    ->setStreet((string) AddressHelper::getStreetName($address->getStreet()))
                    ->setHouseNumber((string) AddressHelper::getHouseNumber($address->getStreet()))
                    ->setCity($address->getCity())
                    ->setPostcode((string) $address->getZipcode())
                    ->setCountry($address->getCountry() instanceof CountryEntity ? (string) $address->getCountry()->getIso() : 'DE')
            )
            ->setCustomData([
                'source' => 'Shopware',
            ]);

        $requestModel->setRepresentatives([
            (new BuyerRepresentative())
                ->setSalutation($this->getMappedSalutationFromAddress($address))
                ->setFirstName($address->getFirstname())
                ->setLastName($address->getLastname())
                ->setEmail($customer->getEmail())
                ->setPhone($address->getPhoneNumber())
                ->setAddress($requestModel->getBusinessAddress())
                ->setBirthDate($customer->getBirthday()),
        ]);

        // TODO add event dispatcher

        return $requestModel; // @phpstan-ignore-line
    }
}
