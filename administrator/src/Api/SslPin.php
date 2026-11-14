<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Public-key pinning for SDP servers reached by IP address or with a self-signed certificate.
 *
 * A pin is "sha256//" + base64(SHA-256 of the certificate's DER SubjectPublicKeyInfo), the format
 * cURL expects for CURLOPT_PINNEDPUBLICKEY. Several pins may be given, separated by ";", to allow
 * a certificate change without downtime.
 */
final class SslPin
{
    private const ONE = '/^sha256\/\/[A-Za-z0-9+\/]{43}=$/';

    /**
     * Validate and normalise a pin list. Returns '' for empty input.
     *
     * @throws \InvalidArgumentException
     */
    public static function normalize(string $value): string
    {
        $pins = [];

        foreach (preg_split('/[;,\s]+/', trim($value)) ?: [] as $pin) {
            if ($pin === '') {
                continue;
            }

            if (!preg_match(self::ONE, $pin)) {
                throw new \InvalidArgumentException('Invalid pin format');
            }

            $pins[$pin] = true;
        }

        if (\count($pins) > 5) {
            throw new \InvalidArgumentException('Too many pins');
        }

        return implode(';', array_keys($pins));
    }

    /**
     * Pin of a PEM "PUBLIC KEY" (as returned by openssl_pkey_get_details()).
     */
    public static function fromPublicKeyPem(string $pem): string
    {
        if (!preg_match('/-----BEGIN PUBLIC KEY-----(.+?)-----END PUBLIC KEY-----/s', $pem, $m)) {
            throw new \InvalidArgumentException('Not a PEM public key');
        }

        $der = base64_decode(preg_replace('/\s+/', '', $m[1]) ?? '', true);

        if ($der === false || $der === '') {
            throw new \InvalidArgumentException('Invalid PEM body');
        }

        return 'sha256//' . base64_encode(hash('sha256', $der, true));
    }

    /**
     * Connect to the server WITHOUT verification and read its certificate, so the admin can see
     * the pin and compare it with the value computed on the SDP server itself. Never trust the
     * result blindly: it is only as good as the network path at that moment.
     *
     * @return array{pin: string, subject: string, issuer: string, expires: ?int}
     *
     * @throws \RuntimeException
     */
    public static function readFromServer(string $baseUrl, int $timeout = 10, string $connectVia = ''): array
    {
        $parts = parse_url($baseUrl);

        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            throw new \RuntimeException('Pinning needs an https base URL');
        }

        $host = trim($parts['host'], '[]');
        $port = (int) ($parts['port'] ?? 443);

        // Same path as the API calls: connect to "connect via", present the base URL's name (SNI).
        $target = $connectVia !== ''
            ? ClientConfig::normalizeConnectVia($connectVia, $port)
            : (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? '[' . $host . ']' : $host) . ':' . $port;
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'SNI_enabled'       => true,
            'peer_name'         => $host,
        ]]);

        $errno  = 0;
        $errstr = '';
        $socket = @stream_socket_client('ssl://' . $target, $errno, $errstr, max(1, $timeout), STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            throw new \RuntimeException('Could not open a TLS connection');
        }

        $params = stream_context_get_params($socket);
        fclose($socket);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $key  = $cert ? openssl_pkey_get_public($cert) : false;
        $info = $cert ? openssl_x509_parse($cert) : false;

        if ($key === false || !\is_array($info)) {
            throw new \RuntimeException('The server sent no readable certificate');
        }

        $details = openssl_pkey_get_details($key);

        return [
            'pin'     => self::fromPublicKeyPem((string) ($details['key'] ?? '')),
            'subject' => (string) ($info['subject']['CN'] ?? ''),
            'issuer'  => (string) ($info['issuer']['CN'] ?? ''),
            'expires' => isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : null,
        ];
    }
}
