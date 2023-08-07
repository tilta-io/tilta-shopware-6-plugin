<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function getLegalForms(): array
    {
        return array_map(fn (string $legalForm): array => [
            'value' => $legalForm,
            'label' => $this->translator->trans('tilta.legalForms.' . $legalForm),
        ], LegalFormEnum::LEGAL_FORMS);
    }
}
