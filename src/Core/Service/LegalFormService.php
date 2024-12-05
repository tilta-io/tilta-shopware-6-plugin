<?php

/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Service;

use Psr\Cache\CacheItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Tilta\Sdk\Model\Request\Util\GetLegalFormsRequestModel;
use Tilta\Sdk\Service\Request\Util\GetLegalFormsRequest;

class LegalFormService
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly GetLegalFormsRequest $legalFormsRequest
    ) {
    }

    public function getLegalForms(string $countryCode): array
    {
        $cacheKey = 'tilta-legal-forms-' . $countryCode;
        $return = $this->cache->get($cacheKey, function (CacheItemInterface $item): array {
            /** @noinspection PhpExpressionResultUnusedInspection */
            $item->expiresAfter(3600 * 4); // cache results for 4 hours

            $responseModel = $this->legalFormsRequest->execute(new GetLegalFormsRequestModel());

            $options = [];
            foreach ($responseModel->getItems() as $code => $label) {
                $options[] = [
                    'value' => $code,
                    'label' => $label,
                ];
            }

            return $options;
        });

        // @phpstan-ignore-next-line
        return is_array($return) ? $return : [];
    }

    public function getLegalFormsOnlyCodes(string $countryCode): array
    {
        return array_map(static fn (array $item) => $item['value'], $this->getLegalForms($countryCode));
    }
}
