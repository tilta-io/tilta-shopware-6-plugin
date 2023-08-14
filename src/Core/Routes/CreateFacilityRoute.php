<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Routes;

use DateTime;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\GenericStoreApiResponse;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationList;
use Tilta\Sdk\Enum\LegalFormEnum;
use Tilta\Sdk\Exception\TiltaException;
use Tilta\TiltaPaymentSW6\Core\Exception\MissingBuyerInformationException;
use Tilta\TiltaPaymentSW6\Core\Service\BuyerService;
use Tilta\TiltaPaymentSW6\Core\Service\FacilityService;

class CreateFacilityRoute
{
    private DataValidator $dataValidator;

    private BuyerService $buyerService;

    private FacilityService $facilityService;

    private EntityRepository $addressRepository;

    private EntityRepository $salutationRepository;

    private LoggerInterface $logger;

    public function __construct(
        DataValidator $dataValidator,
        BuyerService $buyerService,
        FacilityService $facilityService,
        EntityRepository $addressRepository,
        EntityRepository $salutationRepository,
        LoggerInterface $logger
    ) {
        $this->dataValidator = $dataValidator;
        $this->buyerService = $buyerService;
        $this->facilityService = $facilityService;
        $this->addressRepository = $addressRepository;
        $this->salutationRepository = $salutationRepository;
        $this->logger = $logger;
    }

    public function requestFacilityPost(RequestDataBag $requestDataBag, CustomerAddressEntity $address): Response
    {
        if ($requestDataBag->has('incorporatedAtDay') && $requestDataBag->has('incorporatedAtMonth') && $requestDataBag->has('incorporatedAtYear')) {
            $requestDataBag->set('incorporatedAt', sprintf('%02d-%02d-%02d', $requestDataBag->getAlnum('incorporatedAtYear'), $requestDataBag->getAlnum('incorporatedAtMonth'), $requestDataBag->getAlnum('incorporatedAtDay')));
        }

        // TODO: use \Shopware\Core\Framework\Rule\RuleConstraints in the future
        $this->dataValidator->validate(
            $requestDataBag->all(),
            (new DataValidationDefinition())
                ->add('salutationId', new NotBlank(), new Choice($this->getSalutationIds()))
                ->add('phoneNumber', new NotBlank(), new Type('string'))
                ->add('legalForm', new NotBlank(), new Choice(LegalFormEnum::LEGAL_FORMS))
                ->add('incorporatedAt', new NotBlank(), new Type('string'), new Date())
        );

        try {
            $this->buyerService->updateCustomerAddressData($address, [
                'salutationId' => $requestDataBag->getAlnum('salutationId'),
                'phoneNumber' => $requestDataBag->getAlnum('phoneNumber'),
                'legalForm' => $requestDataBag->getAlnum('legalForm'),
                'incorporatedAt' => DateTime::createFromFormat('Y-m-d', $requestDataBag->getAlnum('incorporatedAt')),
            ]);

            // reload address, because entity data has been changed.
            $addressCriteria = (new Criteria([$address->getId()]))
                ->addAssociation('customer');
            $address = $this->addressRepository->search($addressCriteria, Context::createDefaultContext())->first();

            $this->facilityService->createFacilityForBuyerIfNotExist($address, true);
        } catch (MissingBuyerInformationException $missingBuyerInformationException) {
            throw new ConstraintViolationException(new ConstraintViolationList($missingBuyerInformationException->getErrorMessages()), $requestDataBag->all());
        } catch (TiltaException $tiltaException) {
            $this->logger->error('Error during creation of Tilta facility for buyer', [
                /** @phpstan-ignore-next-line */
                'user-id' => $address->getCustomer()->getId(),
                'address-id' => $address->getId(),
                'error-message' => $tiltaException->getMessage(),
            ]);

            return new GenericStoreApiResponse(Response::HTTP_INTERNAL_SERVER_ERROR, new ArrayStruct([
                'success' => false,
                'error' => $tiltaException->getMessage(),
            ]));
        }

        return new SuccessResponse();
    }

    /**
     * @return string[]
     */
    private function getSalutationIds(): array
    {
        /** @var string[] $list */
        $list = $this->salutationRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        return array_values($list);
    }
}
