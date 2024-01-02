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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Tilta\TiltaPaymentSW6\Core\Routes\AbstractBuyerRequestFormDataRoute;
use Tilta\TiltaPaymentSW6\Core\Routes\CreateFacilityRoute;
use Tilta\TiltaPaymentSW6\Core\Routes\GetAddressesWithFacilityRoute;

/**
 * @Route(path="/account/credit-facilities", defaults={"_loginRequired"=true, "_routeScope"={"storefront"}})
 */
class AccountFacilityController extends StorefrontController
{
    private AbstractListAddressRoute $listAddressRoute;

    private CreateFacilityRoute $createFacilityRoute;

    private GetAddressesWithFacilityRoute $addressesWithFacilityRoute;

    private AbstractBuyerRequestFormDataRoute $buyerRequestFormDataRoute;

    public function __construct(
        AbstractListAddressRoute $listAddressRoute,
        CreateFacilityRoute $createFacilityRoute,
        GetAddressesWithFacilityRoute $addressesWithFacilityRoute,
        AbstractBuyerRequestFormDataRoute $buyerRequestFormDataRoute
    ) {
        $this->listAddressRoute = $listAddressRoute;
        $this->createFacilityRoute = $createFacilityRoute;
        $this->addressesWithFacilityRoute = $addressesWithFacilityRoute;
        $this->buyerRequestFormDataRoute = $buyerRequestFormDataRoute;
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

        $data = $this->buyerRequestFormDataRoute->getRequestFormData(new RequestDataBag($request->request->all()), $context, $address);

        return $this->render('@TiltaStorefront/storefront/page/account/tilta-credit-facilities/request-form.html.twig', $data->getObject()->getVars());
    }

    /**
     * @Route(name="frontend.account.tilta.credit-facility.requestForm.post", path="/request/{addressId}", methods={"POST"})
     */
    public function requestFacilityPost(Context $context, RequestDataBag $requestData, CustomerEntity $customerEntity, string $addressId): Response
    {
        try {
            $response = $this->createFacilityRoute->requestFacilityPost($context, $requestData, $customerEntity, $addressId);
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
}
