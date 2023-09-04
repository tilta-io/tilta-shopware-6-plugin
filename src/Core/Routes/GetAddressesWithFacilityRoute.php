<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Response\Facility;
use Tilta\TiltaPaymentSW6\Core\Routes\Response\AddressesWithFacilityResponse;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;

class GetAddressesWithFacilityRoute
{
    private AbstractListAddressRoute $listAddressRoute;

    private FacilityService $facilityService;

    private LoggerInterface $logger;

    public function __construct(
        AbstractListAddressRoute $listAddressRoute,
        FacilityService $facilityService,
        LoggerInterface $logger
    ) {
        $this->listAddressRoute = $listAddressRoute;
        $this->facilityService = $facilityService;
        $this->logger = $logger;
    }

    public function listCreditFacilities(SalesChannelContext $salesChannelContext, CustomerEntity $customer): AddressesWithFacilityResponse
    {
        $criteria = (new Criteria())
            ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('company', null)]));

        $addressesResponse = $this->listAddressRoute->load($criteria, $salesChannelContext, $customer);

        $addressList = $addressesResponse->getAddressCollection()->getElements();

        foreach ($addressList as $address) {
            $address->setCustomer($customer);
            try {
                $facility = $this->facilityService->getFacility($address);
                if ($facility instanceof Facility) {
                    $address->addExtension('tiltaFacility', new ArrayStruct($facility->toArray()));
                }
            } catch (TiltaException $tiltaException) {
                $this->logger->error(sprintf('Error during fetching facilities for address. stop fetching other facilities. (%s)', $tiltaException->getMessage()), [
                    'customer-id' => $customer->getId(),
                    'address-id' => $address->getId(),
                ]);
                break;
            }
        }

        return new AddressesWithFacilityResponse($addressList);
    }
}
