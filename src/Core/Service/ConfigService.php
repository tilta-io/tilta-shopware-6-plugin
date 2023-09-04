<?php
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Tilta\TiltaPaymentSW6\Core\Service;

use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function isConfigReady(): bool
    {
        try {
            if ($this->systemConfigService->getBool('TiltaPaymentSW6.config.sandbox')) {
                $authToken = $this->systemConfigService->getString('TiltaPaymentSW6.config.sandboxApiAuthToken');
                $merchantExternalId = $this->systemConfigService->getString('TiltaPaymentSW6.config.sandboxApiMerchantExternalId');
            } else {
                $authToken = $this->systemConfigService->getString('TiltaPaymentSW6.config.liveApiAuthToken');
                $merchantExternalId = $this->systemConfigService->getString('TiltaPaymentSW6.config.liveApiMerchantExternalId');
            }

            return !empty($authToken) && !empty($merchantExternalId);
        } catch (InvalidSettingValueException $invalidSettingValueException) {
            return false;
        }
    }

    public function isSandboxEnabled(): bool
    {
        return $this->systemConfigService->getBool('TiltaPaymentSW6.config.sandbox');
    }

    public function getMerchantExternalId(): string
    {
        if ($this->isSandboxEnabled()) {
            return $this->systemConfigService->getString('TiltaPaymentSW6.config.sandboxApiMerchantExternalId');
        }

        return $this->systemConfigService->getString('TiltaPaymentSW6.config.liveApiMerchantExternalId');
    }
}
