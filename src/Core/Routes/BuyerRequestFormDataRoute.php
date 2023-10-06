<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\Request;
use Tilta\TiltaPaymentSW6\Core\Extension\CustomerAddressEntityExtension;
use Tilta\TiltaPaymentSW6\Core\Extension\Entity\TiltaCustomerAddressDataEntity;
use Tilta\TiltaPaymentSW6\Core\Routes\Response\BuyerRequestFormDataResponse;
use Tilta\TiltaPaymentSW6\Core\Service\LegalFormService;
use Tilta\TiltaPaymentSW6\Core\Util\EntityHelper;

class BuyerRequestFormDataRoute extends AbstractBuyerRequestFormDataRoute
{
    private LegalFormService $legalFormService;

    private AbstractSalutationRoute $salutationRoute;

    private EntityHelper $entityHelper;

    public function __construct(
        LegalFormService $legalFormService,
        AbstractSalutationRoute $salutationRoute,
        EntityHelper $entityHelper
    )
    {
        $this->legalFormService = $legalFormService;
        $this->salutationRoute = $salutationRoute;
        $this->entityHelper = $entityHelper;
    }

    public function getDecorated(): AbstractBuyerRequestFormDataRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function getRequestFormData(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerAddressEntity $customerAddress): BuyerRequestFormDataResponse
    {
        /** @var TiltaCustomerAddressDataEntity|null $tiltaData */
        $tiltaData = $customerAddress->getExtension(CustomerAddressEntityExtension::TILTA_DATA);

        return new BuyerRequestFormDataResponse(new ArrayStruct([
            'page' => [
                'address' => $customerAddress,
                'salutations' => $this->getSalutations($context),
                'legalForms' => $this->legalFormService->getLegalForms(),
            ],
            'data' => new ArrayStruct([
                'salutationId' => $requestDataBag->get('salutationId', $customerAddress->getSalutationId()),
                'phoneNumber' => $requestDataBag->get('phoneNumber', $customerAddress->getPhoneNumber()),
                'legalForm' => $requestDataBag->get('legalForm', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getLegalForm() : null),
                'incorporatedAtYear' => $requestDataBag->get('incorporatedAtDay', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getIncorporatedAt()->format('Y') : null),
                'incorporatedAtMonth' => $requestDataBag->get('incorporatedAtDay', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getIncorporatedAt()->format('m') : null),
                'incorporatedAtDay' => $requestDataBag->get('incorporatedAtDay', $tiltaData instanceof TiltaCustomerAddressDataEntity ? $tiltaData->getIncorporatedAt()->format('d') : null),
            ]),
        ]));
    }

    private function getSalutations(SalesChannelContext $context): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $context, new Criteria())->getSalutations();

        $salutations->sort(static fn (SalutationEntity $a, SalutationEntity $b): int => $b->getSalutationKey() <=> $a->getSalutationKey());

        return $salutations;
    }
}
