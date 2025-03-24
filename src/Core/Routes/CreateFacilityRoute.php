<?php

/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use DateTime;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Type;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Routes\Response\ErrorResponse;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Service\LegalFormService;
use UnexpectedValueException;

#[Route(path: '/store-api/tilta', defaults: [
    '_loginRequired' => true,
    '_loginRequiredAllowGuest' => true,
    '_routeScope' => ['store-api'],
])]
class CreateFacilityRoute
{
    /**
     * @param EntityRepository<EntityCollection<CustomerAddressEntity>> $addressRepository
     * @param EntityRepository<EntityCollection<SalutationEntity>> $salutationRepository
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly BuyerService $buyerService,
        private readonly FacilityService $facilityService,
        private readonly EntityRepository $addressRepository,
        private readonly EntityRepository $salutationRepository,
        private readonly LoggerInterface $logger,
        private readonly LegalFormService $legalFormService
    ) {
    }

    #[Route(path: '/facility/create/{addressId}', methods: ['POST'])]
    public function requestFacilityPost(Context $context, RequestDataBag $requestDataBag, CustomerEntity $customer, string $addressId): Response
    {
        $customerAddress = $this->getAddressForCustomer($customer, $addressId, $context);
        if (!$customerAddress instanceof CustomerAddressEntity) {
            throw new AddressNotFoundException($addressId);
        }

        /** @var CountryEntity $country */ // country is always loaded
        $country = $customerAddress->getCountry();


        if ($requestDataBag->has('incorporatedAtMonth') && $requestDataBag->has('incorporatedAtYear')) {
            try {
                if (!$requestDataBag->has('incorporatedAtDay') || $requestDataBag->get('incorporatedAtDay') === null) {
                    $requestDataBag->set('incorporatedAtDay', 1);
                }

                $requestDataBag->set('incorporatedAt', sprintf('%02d-%02d-%02d', $requestDataBag->getAlnum('incorporatedAtYear'), $requestDataBag->getAlnum('incorporatedAtMonth'), $requestDataBag->getAlnum('incorporatedAtDay')));
            } catch (UnexpectedValueException) {
                // do nothing. Validation exception got thrown later.
            }
        }

        $validationDefinitions = (new DataValidationDefinition())
            ->add('phoneNumber', new Type('string'), new Regex('/^\+[1-9]{2}\d+/'))
            ->add('legalForm', new NotBlank(), new Choice($this->legalFormService->getLegalFormsOnlyCodes($country->getIso() ?? '-')))
            ->add('toc', new NotBlank(), new EqualTo('1'));

        if ($requestDataBag->get('legalForm') === 'SOLE_TRADER') {
            $validationDefinitions->add('incorporatedAt', new NotBlank(), new Type('string'), new Date());
            $validationDefinitions->add('salutationId', new NotBlank(), new Choice($this->getSalutationIds($context)));
        }

        // TODO: use \Shopware\Core\Framework\Rule\RuleConstraints in the future
        $this->dataValidator->validate($requestDataBag->all(), $validationDefinitions);

        try {
            $incorporatedAt = $requestDataBag->get('incorporatedAt');
            $this->buyerService->updateCustomerAddressData(
                $customerAddress,
                [
                    'salutationId' => $requestDataBag->getAlnum('salutationId'),
                    'phoneNumber' => $requestDataBag->get('phoneNumber'),
                    'legalForm' => $requestDataBag->get('legalForm'),
                    'incorporatedAt' => is_string($incorporatedAt) ? DateTime::createFromFormat('Y-m-d', $incorporatedAt) : null,
                ],
                $context
            );

            // reload address, because entity data has been changed. - address will be never null
            /** @var CustomerAddressEntity $customerAddress */
            $customerAddress = $this->getAddressForCustomer($customer, $addressId, $context);

            $this->facilityService->createFacilityForBuyerIfNotExist($context, $customerAddress, true);
        } catch (MissingBuyerInformationException $missingBuyerInformationException) {
            return new ErrorResponse($missingBuyerInformationException->getErrorMessages(), Response::HTTP_BAD_REQUEST);
        } catch (TiltaException $tiltaException) {
            $this->logger->error('Error during creation of Tilta facility for buyer', [
                'user-id' => $customer->getId(),
                'address-id' => $customerAddress->getId(),
                'error-message' => $tiltaException->getMessage(),
            ]);

            return new ErrorResponse([$tiltaException->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new SuccessResponse();
    }

    private function getAddressForCustomer(CustomerEntity $customer, string $addressId, Context $context): ?CustomerAddressEntity
    {
        // reload address, because entity data has been changed.
        $addressCriteria = (new Criteria([$addressId]))
            ->addFilter(new EqualsFilter('customerId', $customer->getId()))
            ->addAssociation('country')
            ->addAssociation('customer');

        $address = $this->addressRepository->search($addressCriteria, $context)->first();

        // check is only for PHPStan
        return $address instanceof CustomerAddressEntity ? $address : null;
    }

    /**
     * @return string[]
     */
    private function getSalutationIds(Context $context): array
    {
        /** @var string[] $list */
        $list = $this->salutationRepository->searchIds(new Criteria(), $context)->getIds();

        return array_values($list);
    }
}
