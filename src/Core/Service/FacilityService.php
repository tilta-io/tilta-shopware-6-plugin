<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
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

class FacilityService
{
    private ContainerInterface $container;

    private BuyerService $buyerService;

    public function __construct(
        ContainerInterface $container,
        BuyerService $buyerService
    ) {
        $this->container = $container;
        $this->buyerService = $buyerService;
    }

    /**
     * @throws TiltaException
     * @throws MissingBuyerInformationException
     * @throws BuyerNotFoundException
     */
    public function createFacilityForBuyerIfNotExist(CustomerAddressEntity $address, bool $withBuyerUpdate = false): Facility
    {
        $buyerExternalId = BuyerService::generateBuyerExternalId($address);
        /** @var TiltaCustomerAddressDataEntity $tiltaData */
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        if (!$tiltaData instanceof TiltaCustomerAddressDataEntity || ($tiltaData->getBuyerExternalId() === null || $tiltaData->getBuyerExternalId() === '')) {
            $this->buyerService->createBuyerIfNotExist($address);
        } elseif ($withBuyerUpdate) {
            $this->buyerService->updateBuyer($address);
        }

        try {
            /** @var CreateFacilityRequest $createFacilityRequest */
            $createFacilityRequest = $this->container->get(CreateFacilityRequest::class);
            $createFacilityRequest->execute(new CreateFacilityRequestModel($buyerExternalId));
        } catch (DuplicateFacilityException $duplicateFacilityException) {
            // do nothing - just jump into finally.
        } finally {
            $facility = $this->getFacility($address);
        }

        /** @phpstan-ignore-next-line */
        return $facility;
    }

    public function getFacility(CustomerAddressEntity $address): ?Facility
    {
        /** @var GetFacilityRequest $request */
        $request = $this->container->get(GetFacilityRequest::class);

        try {
            return $request->execute(new GetFacilityRequestModel(BuyerService::generateBuyerExternalId($address)));
        } catch (NoActiveFacilityFoundException $noActiveFacilityFoundException) {
            return null;
        }
    }
}
