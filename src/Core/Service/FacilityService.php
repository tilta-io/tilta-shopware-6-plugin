<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Service;

use DateTimeInterface;
use Exception;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Tilta\Sdk\Exception\GatewayException\Facility\DuplicateFacilityException;
use Tilta\Sdk\Exception\GatewayException\Facility\NoActiveFacilityFoundException;
use Tilta\Sdk\Exception\GatewayException\NotFoundException\BuyerNotFoundException;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Request\Facility\CreateFacilityRequestModel;
use Tilta\Sdk\Model\Request\Facility\GetFacilityRequestModel;
use Tilta\Sdk\Model\Response\Facility;
use Tilta\Sdk\Service\Request\Facility\CreateFacilityRequest;
use Tilta\Sdk\Service\Request\Facility\GetFacilityRequest;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Util\AmountHelper;

class FacilityService
{
    private ContainerInterface $container;

    private BuyerService $buyerService;

    /**
     * @var EntityRepository<EntityCollection<TiltaCustomerAddressDataEntity>>
     */
    private EntityRepository $tiltaDataRepository;

    /**
     * @param EntityRepository<EntityCollection<TiltaCustomerAddressDataEntity>> $tiltaDataRepository
     */
    public function __construct(
        ContainerInterface $container,
        BuyerService $buyerService,
        EntityRepository $tiltaDataRepository
    ) {
        $this->container = $container;
        $this->buyerService = $buyerService;
        $this->tiltaDataRepository = $tiltaDataRepository;
    }

    /**
     * @throws TiltaException
     * @throws MissingBuyerInformationException
     * @throws BuyerNotFoundException
     */
    public function createFacilityForBuyerIfNotExist(Context $context, CustomerAddressEntity $address, bool $withBuyerUpdate = false): Facility
    {
        $buyerExternalId = BuyerService::generateBuyerExternalId($address);
        /** @var TiltaCustomerAddressDataEntity $tiltaData */
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        if (!$tiltaData instanceof TiltaCustomerAddressDataEntity || ($tiltaData->getBuyerExternalId() === null || $tiltaData->getBuyerExternalId() === '')) {
            $this->buyerService->createBuyerIfNotExist($address, $context);
        } elseif ($withBuyerUpdate) {
            $this->buyerService->updateBuyer($address, $context);
        }

        try {
            /** @var CreateFacilityRequest $createFacilityRequest */
            $createFacilityRequest = $this->container->get(CreateFacilityRequest::class);
            $createFacilityRequest->execute(new CreateFacilityRequestModel($buyerExternalId));
        } catch (DuplicateFacilityException $duplicateFacilityException) {
            // do nothing - just jump into finally.
        } finally {
            $facility = $this->getFacility($address, $context);
            if ($facility instanceof Facility) {
                $this->updateFacilityOnCustomerAddress($context, $address, $facility);
            }
        }

        /** @phpstan-ignore-next-line */
        return $facility;
    }

    public function getFacility(CustomerAddressEntity $address, Context $context): ?Facility
    {
        /** @var GetFacilityRequest $request */
        $request = $this->container->get(GetFacilityRequest::class);

        try {
            $facility = $request->execute(new GetFacilityRequestModel(BuyerService::generateBuyerExternalId($address)));
            $this->updateFacilityOnCustomerAddress($context, $address, $facility);

            return $facility;
        } catch (NoActiveFacilityFoundException $noActiveFacilityFoundException) {
            return null;
        }
    }

    public function updateFacilityOnCustomerAddress(Context $context, CustomerAddressEntity $customerAddress, Facility $facility = null): void
    {
        $this->tiltaDataRepository->upsert([[
            TiltaCustomerAddressDataEntity::FIELD_CUSTOMER_ADDRESS_ID => $customerAddress->getId(),
            TiltaCustomerAddressDataEntity::FIELD_TOTAL_AMOUNT => $facility instanceof Facility ? $facility->getTotalAmount() : null,
            TiltaCustomerAddressDataEntity::FIELD_VALID_UNTIL => $facility instanceof Facility ? $facility->getExpiresAt() : null,
        ]], $context);
    }

    public function checkCartAmount(CustomerAddressEntity $customerAddress, CartPrice $price, Context $context): bool
    {
        /** @var TiltaCustomerAddressDataEntity|null $tiltaData */
        $tiltaData = $customerAddress->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        if (!$tiltaData instanceof TiltaCustomerAddressDataEntity) {
            // facility is "valid" if tiltaData does not exist - customer may create a buyer.
            return true;
        }

        if (!$tiltaData->getValidUntil() instanceof DateTimeInterface) {
            // facility seems to be invalid
            return false;
        }

        if ($tiltaData->getValidUntil()->getTimestamp() < time()) {
            try {
                // fetching facility will update the facility in the database
                $this->getFacility($customerAddress, $context);
            } catch (Exception $exception) {
                // error during facility fetching -> facility seems to be not valid
                return false;
            }
        }

        return $tiltaData->getTotalAmount() >= AmountHelper::toSdk($price->getTotalPrice());
    }
}
