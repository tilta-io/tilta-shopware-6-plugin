<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Storefront\Controller;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\AddressNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Routes\CreateFacilityRoute;
use Tilta\TiltaPaymentSW6\Core\Routes\GetAddressesWithFacilityRoute;
use Tilta\TiltaPaymentSW6\Core\Service\LegalFormService;

/**
 * @Route(path="/account/credit-facilities", defaults={"_loginRequired"=true, "_routeScope"={"storefront"}})
 */
class AccountFacilityController extends StorefrontController
{
    private AbstractListAddressRoute $listAddressRoute;

    private AbstractSalutationRoute $salutationRoute;

    private LegalFormService $legalFormService;

    private CreateFacilityRoute $createFacilityRoute;

    private GetAddressesWithFacilityRoute $addressesWithFacilityRoute;

    public function __construct(
        AbstractListAddressRoute $listAddressRoute,
        AbstractSalutationRoute $salutationRoute,
        LegalFormService $legalFormService,
        CreateFacilityRoute $createFacilityRoute,
        GetAddressesWithFacilityRoute $addressesWithFacilityRoute
    ) {
        $this->listAddressRoute = $listAddressRoute;
        $this->salutationRoute = $salutationRoute;
        $this->legalFormService = $legalFormService;
        $this->createFacilityRoute = $createFacilityRoute;
        $this->addressesWithFacilityRoute = $addressesWithFacilityRoute;
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

        return $this->render('@TiltaStorefront/storefront/page/account/tilta-credit-facilities/index.html.twig', [
            'addressList' => $this->addressesWithFacilityRoute->listCreditFacilities($salesChannelContext, $customer)->getAddressList(),
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
    public function requestFacilityPost(RequestDataBag $requestData, CustomerEntity $customerEntity, string $addressId): Response
    {
        try {
            $response = $this->createFacilityRoute->requestFacilityPost($requestData, $customerEntity, $addressId);
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
        } catch (AddressNotFoundException $addressNotFoundException) {
            return $this->handleAddressNotFound();
        }

        if ($response instanceof SuccessResponse) {
            $this->addFlash('success', $this->trans('tilta.messages.facility.created-successfully'));

            $backTo = $requestData->get('backTo');
            if (is_string($backTo)) {
                return $this->redirect($backTo);
            }

            return $this->redirectToRoute('frontend.account.tilta.credit-facility.list');
        }

        $this->addFlash('danger', $this->trans('tilta.messages.facility.unknown-error'));

        return $this->forwardToRoute(
            'frontend.account.tilta.credit-facility.requestForm',
            [],
            [
                'addressId' => $addressId,
            ]
        );
    }

    private function handleAddressNotFound(): Response
    {
        $this->addFlash('danger', $this->trans('tilta.messages.address-does-not-exist'));

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
