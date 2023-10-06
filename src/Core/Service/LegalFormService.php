<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Service;

use Symfony\Contracts\Translation\TranslatorInterface;
use Tilta\Sdk\Enum\LegalFormEnum;

class LegalFormService
{
    private TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function getLegalForms(string $countryCode): array
    {
        return array_map(fn (string $legalForm): array => [
            'value' => $legalForm,
            'label' => $this->findTranslation($legalForm),
        ], LegalFormEnum::getLegalFormsForCountry($countryCode));
    }

    private function findTranslation(string $legalForm): string
    {
        $keys = [
            'tilta.legalForms.' . $legalForm,
            'tilta.legalForms.' . LegalFormEnum::removePrefix($legalForm),
        ];

        foreach ($keys as $key) {
            $trans = $this->translator->trans($key);
            if ($trans !== $key) {
                return $trans;
            }
        }

        return $legalForm;
    }
}
