<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Administration\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Tilta\Sdk\Exception\GatewayException;
use Tilta\Sdk\Model\Request\Order\GetOrderListRequestModel;
use Tilta\Sdk\Service\Request\Order\GetOrderListRequest;
use Tilta\Sdk\Util\TiltaClientFactory;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 */
class TestCredentialsController extends AbstractController
{
    /**
     * @Route("/api/tilta/test-api-credentials", name="api.action.tilta.test-api.credentials", methods={"POST"})
     */
    public function testCredentials(Request $request): JsonResponse
    {
        try {
            $client = TiltaClientFactory::getClientInstance(
                (string) $request->request->get('authToken', ''),
                $request->request->getBoolean('isSandbox'),
            );

            // just try to fetch orders. If merchant-id is wrong, an exception got thrown
            (new GetOrderListRequest($client))->execute(
                (new GetOrderListRequestModel())
                    ->setMerchantExternalId((string) $request->request->get('merchantExternalId', ''))
                    ->setLimit(1)
            );
        } catch (GatewayException $gatewayException) {
            return new JsonResponse([
                'success' => false,
                'code' => $gatewayException->getTiltaCode(),
            ]);
        }

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
