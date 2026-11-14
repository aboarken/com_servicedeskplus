<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Psr\Log\LoggerInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\ClientConfig;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpClient;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport\JoomlaHttpTransport;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\Transport\TransportInterface;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\KeyVault;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\KeyVaultException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Security\SecretProvider;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Settings\SettingsRepository;

/**
 * Builds SdpClient from stored settings. The decrypted service key exists only inside the client.
 */
final class SdpClientFactory
{
    private ?KeyVault $vault = null;

    /**
     * @param  (\Closure(ClientConfig): TransportInterface)|null  $transportFactory  Injected for tests
     */
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SecretProvider $secrets,
        private readonly LoggerInterface $logger,
        private readonly ?\Closure $transportFactory = null
    ) {
    }

    /**
     * @throws SdpException  NOT_CONFIGURED when the URL or key is missing or cannot be decrypted
     */
    public function createServiceClient(): SdpClient
    {
        $settings = $this->settings->load();

        if ($settings->baseUrl() === '' || !$settings->hasServiceKey()) {
            throw new SdpException(SdpException::NOT_CONFIGURED, 'ServiceDesk Plus connection is not configured');
        }

        try {
            $config = new ClientConfig(
                $settings->baseUrl(),
                $settings->timeout(),
                $settings->connectTimeout(),
                $settings->verifySsl(),
                $settings->caBundle(),
                $settings->sslPin(),
                $settings->connectVia()
            );
        } catch (\InvalidArgumentException $e) {
            throw new SdpException(SdpException::NOT_CONFIGURED, 'ServiceDesk Plus base URL is invalid', 0, null, '', $e);
        }

        try {
            $key = $this->vault()->decrypt($settings->serviceKeyCiphertext(), KeyVault::CONTEXT_SERVICE_KEY);
        } catch (KeyVaultException $e) {
            $this->logger->error('Service key could not be decrypted; the encryption secret may have changed. Re-enter the key.');

            throw new SdpException(SdpException::NOT_CONFIGURED, 'Service key cannot be decrypted', 0, null, '', $e);
        }

        $transport = $this->transportFactory !== null
            ? ($this->transportFactory)($config)
            : new JoomlaHttpTransport($config);

        return new SdpClient($transport, $config, $key, $this->logger);
    }

    public function vault(): KeyVault
    {
        return $this->vault ??= new KeyVault($this->secrets->getSecret());
    }

    public function secretSource(): string
    {
        return $this->secrets->getSource();
    }
}
