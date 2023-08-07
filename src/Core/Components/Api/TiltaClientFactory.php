<?php

declare(strict_types=1);
/*
 * (c) WEBiDEA
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tilta\TiltaPaymentSW6\Core\Components\Api;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Tilta\Sdk\HttpClient\TiltaClient;
use Tilta\Sdk\Util\Logging;

class TiltaClientFactory
{
    private SystemConfigService $configService;

    private ?LoggerInterface $logger = null;

    public function __construct(SystemConfigService $configService, LoggerInterface $logger = null)
    {
        $this->configService = $configService;
        $this->logger = $logger;
    }

    public function createTiltaClient(): TiltaClient
    {
        if ($this->logger instanceof LoggerInterface) {
            Logging::setPsr3Logger($this->logger);
        }

        $isSandbox = (bool) $this->configService->get('TiltaPaymentSW6.config.sandbox');

        if (!$isSandbox) {
            $token = $this->configService->get('TiltaPaymentSW6.config.liveApiAuthToken');
        } else {
            $token = $this->configService->get('TiltaPaymentSW6.config.sandboxApiAuthToken');
        }

        if ($token !== null && !is_string($token)) {
            throw new RuntimeException('invalid configuration for Tilta token.');
        }

        return \Tilta\Sdk\Util\TiltaClientFactory::getClientInstance((string) $token, $isSandbox);
    }
}
