<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Storefront\Controller;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Date;
use Tilta\Sdk\Enum\LegalFormEnum;
use Tilta\Sdk\Exception\GatewayException\InvalidRequestException;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\Sdk\Model\Response\Facility;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;
use Tilta\TiltaPaymentSW6\Core\Service\LegalFormService;

/**
 * @Route(path="/account/credit-facilities", defaults={"_loginRequired"=true, "_routeScope"={"storefront"}})
 */
class AccountFacilityController extends StorefrontController
{
    private AbstractListAddressRoute $listAddressRoute;

    private AbstractSalutationRoute $salutationRoute;

    private DataValidator $validator;

    private LegalFormService $legalFormService;

    private BuyerService $buyerService;

    private FacilityService $facilityService;

    private LoggerInterface $logger;

    public function __construct(
        AbstractListAddressRoute $listAddressRoute,
        AbstractSalutationRoute $salutationRoute,
        DataValidator $validator,
        LegalFormService $legalFormService,
        BuyerService $buyerService,
        FacilityService $facilityService,
        LoggerInterface $logger
    ) {
        $this->listAddressRoute = $listAddressRoute;
        $this->salutationRoute = $salutationRoute;
        $this->validator = $validator;
        $this->legalFormService = $legalFormService;
        $this->buyerService = $buyerService;
        $this->facilityService = $facilityService;
        $this->logger = $logger;
    }

    /**
     * @Route(name="frontend.account.tilta.credit-facility.list", path="/")
     */
    public function listCreditFacilities(SalesChannelContext $salesChannelContext): Response
    {
        $customer = $salesChannelContext->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return $this->redirectToRoute('frontend.account.login');
        }

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

        return $this->render('@TiltaStorefront/storefront/page/account/tilta-credit-facilities/index.html.twig', [
            'addressList' => $addressList,
        ]);
    }

    /**
     * @Route(name="frontend.account.tilta.credit-facility.requestForm", path="/request/{addressId}", methods={"GET"})
     */
    public function requestFacilityForm(Request $request, SalesChannelContext $context, string $addressId): Response
    {
        $address = $this->getAddressOrRedirect($context, $addressId);

        if ($address instanceof Response) {
            return $address;
        }

        /** @var TiltaCustomerAddressDataEntity|null $tiltaData */
        $tiltaData = $address->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        return $this->render('@TiltaStorefront/storefront/page/account/tilta-credit-facilities/request-form.html.twig', [
            'page' => [
                'address' => $address,
                'salutations' => $this->getSalutations($context),
                'legalForms' => $this->legalFormService->getLegalForms(),
            ],
            'data' => new ArrayStruct([
                'salutationId' => $request->get('salutationId', $address->getSalutationId()),
                'phoneNumber' => $request->get('phoneNumber', $address->getPhoneNumber()),
                'legalForm' => $request->get('legalForm', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getLegalForm() : null),
                'incorporatedAtYear' => $request->get('incorporatedAtDay', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getIncorporatedAt()->format('Y') : null),
                'incorporatedAtMonth' => $request->get('incorporatedAtDay', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getIncorporatedAt()->format('m') : null),
                'incorporatedAtDay' => $request->get('incorporatedAtDay', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getIncorporatedAt()->format('d') : null),
            ]),
        ]);
    }

    /**
     * @Route(name="frontend.account.tilta.credit-facility.requestForm.post", path="/request/{addressId}", methods={"POST"})
     */
    public function requestFacilityPost(Request $request, SalesChannelContext $context, string $addressId): Response
    {
        $address = $this->getAddressOrRedirect($context, $addressId);

        if (!$address instanceof CustomerAddressEntity) {
            return $address;
        }

        $requestData = $request->request->all();

        if (isset($requestData['incorporatedAtDay'], $requestData['incorporatedAtMonth'], $requestData['incorporatedAtYear'])) {
            $requestData['incorporatedAt'] = sprintf('%02d-%02d-%02d', $requestData['incorporatedAtYear'], $requestData['incorporatedAtMonth'], $requestData['incorporatedAtDay']);
        }

        try {
            $this->validator->validate(
                $requestData,
                (new DataValidationDefinition())
                    ->add('salutationId', ...RuleConstraints::choice(array_values($this->getSalutations($context)->getIds())))
                    ->add('phoneNumber', ...RuleConstraints::string())
                    ->add('legalForm', ...RuleConstraints::choice(LegalFormEnum::LEGAL_FORMS))
                    ->add('incorporatedAt', ...RuleConstraints::string(), ...[new Date()])
            );
        } catch (ConstraintViolationException $constraintViolationException) {
            return $this->forwardToRoute(
                'frontend.account.tilta.credit-facility.requestForm',
                [
                    'formViolations' => $constraintViolationException,
                ],
                [
                    'addressId' => $addressId,
                ]
            );
        }

        try {
            $this->buyerService->updateCustomerAddressData($address, [
                'salutationId' => $requestData['salutationId'],
                'phoneNumber' => $requestData['phoneNumber'],
                'legalForm' => $requestData['legalForm'],
                'incorporatedAt' => DateTime::createFromFormat('Y-m-d', $requestData['incorporatedAt']),
            ]);

            // reload address, because entity data has been changed.
            // added doc for PHPStan: redirect got never return.
            /** @var CustomerAddressEntity $address */
            $address = $this->getAddressOrRedirect($context, $address->getId());

            $this->facilityService->createFacilityForBuyerIfNotExist($address, true);
        } catch (MissingBuyerInformationException $missingBuyerInformationException) {
            $exception = $missingBuyerInformationException;
            foreach ($missingBuyerInformationException->getErrorMessages() as $message) {
                $this->addFlash('danger', $message);
            }
        } catch (InvalidRequestException $invalidRequestException) {
            $exception = $invalidRequestException;
            $this->addFlash('danger', $invalidRequestException->getMessage());
        } catch (TiltaException $tiltaException) {
            $exception = $tiltaException;
            $this->addFlash('danger', $this->trans('tilta.messages.facility.unknown-error'));
        } finally {
            if (isset($exception) && $exception instanceof Exception) {
                $this->logger->error('Error during creation of Tilta facility for buyer', [
                    /** @phpstan-ignore-next-line */
                    'user-id' => $address->getCustomer()->getId(),
                    'address-id' => $address->getId(),
                    'error-message' => $exception->getMessage(),
                ]);

                return $this->forwardToRoute(
                    'frontend.account.tilta.credit-facility.requestForm',
                    [],
                    [
                        'addressId' => $addressId,
                    ]
                );
            }
        }

        $this->addFlash('success', $this->trans('tilta.messages.facility.created-successfully'));

        return $this->redirectToRoute('frontend.account.tilta.credit-facility.list');
    }

    /**
     * @return CustomerAddressEntity|RedirectResponse
     */
    private function getAddressOrRedirect(SalesChannelContext $context, string $addressId)
    {
        $customer = $context->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return $this->redirectToRoute('frontend.account.login');
        }

        $criteria = (new Criteria([$addressId]))
            ->addAssociation('customer')
            ->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('company', null)]));

        $addressesResponse = $this->listAddressRoute->load($criteria, $context, $customer);

        /** @var CustomerAddressEntity|null $address */
        $address = $addressesResponse->getAddressCollection()->first();

        if (!$address instanceof CustomerAddressEntity) {
            $this->addFlash('danger', $this->trans('tilta.messages.address-does-not-exist'));

            return $this->redirectToRoute('frontend.account.tilta.credit-facility.list');
        }

        return $address;
    }

    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext, new Criteria())->getSalutations();

        $salutations->sort(static fn (SalutationEntity $a, SalutationEntity $b): int => $b->getSalutationKey() <=> $a->getSalutationKey());

        return $salutations;
    }
}
