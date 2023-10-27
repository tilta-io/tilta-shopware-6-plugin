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
    private CacheInterface $cache;

    private GetLegalFormsRequest $legalFormsRequest;

    public function __construct(CacheInterface $cache, GetLegalFormsRequest $legalFormsRequest)
    {
        $this->cache = $cache;
        $this->legalFormsRequest = $legalFormsRequest;
    }

    public function getLegalForms(string $countryCode): array
    {
        $cacheKey = 'tilta-legal-forms-' . $countryCode;
        $return = $this->cache->get($cacheKey, function (CacheItemInterface $item) use ($countryCode): array {
            /** @noinspection PhpExpressionResultUnusedInspection */
            $item->expiresAfter(3600 * 4); // cache results for 4 hours

            $responseModel = $this->legalFormsRequest->execute(new GetLegalFormsRequestModel($countryCode));

            $options = [];
            foreach ($responseModel->getItems() as $code => $label) {
                $options[] = [
                    'value' => $code,
                    'label' => $label,
                ];
            }

            return $options;
        });

        return is_array($return) ? $return : [];
    }

    public function getLegalFormsOnlyCodes(string $countryCode): array
    {
        return array_map(static fn (array $item) => $item['value'], $this->getLegalForms($countryCode));
    }
}
