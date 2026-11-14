<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Connection settings for the SDP server. Contains no secrets.
 */
final class ClientConfig
{
    public readonly string $baseUrl;

    /** Normalised pin list ("sha256//...;sha256//..."), or '' when not pinning. */
    public readonly string $pinnedPublicKey;

    /**
     * "host:port" to open the TCP connection to instead of the base URL's host, or ''.
     * The base URL's host is still used for the certificate check (SNI) and the Host header.
     */
    public readonly string $connectVia;

    public function __construct(
        string $baseUrl,
        public readonly int $timeout = 20,
        public readonly int $connectTimeout = 5,
        public readonly bool $verifySsl = true,
        public readonly string $caBundle = '',
        string $pinnedPublicKey = '',
        string $connectVia = ''
    ) {
        $this->baseUrl         = self::normalizeBaseUrl($baseUrl);
        $this->pinnedPublicKey = SslPin::normalize($pinnedPublicKey);
        $this->connectVia      = self::normalizeConnectVia($connectVia, $this->port());

        if ($timeout < 1 || $timeout > 300 || $connectTimeout < 1 || $connectTimeout > $timeout) {
            throw new \InvalidArgumentException('Invalid timeout values');
        }

        if ($this->pinnedPublicKey !== '' && !$this->isHttps()) {
            throw new \InvalidArgumentException('Certificate pinning needs an https base URL');
        }
    }

    public function isPinned(): bool
    {
        return $this->pinnedPublicKey !== '';
    }

    public function host(): string
    {
        return (string) parse_url($this->baseUrl, PHP_URL_HOST);
    }

    public function port(): int
    {
        $port = parse_url($this->baseUrl, PHP_URL_PORT);

        return $port ? (int) $port : ($this->isHttps() ? 443 : 80);
    }

    /**
     * cURL CURLOPT_CONNECT_TO entry: "base-host:base-port:via-host:via-port", or null.
     */
    public function connectToEntry(): ?string
    {
        if ($this->connectVia === '') {
            return null;
        }

        return $this->host() . ':' . $this->port() . ':' . $this->connectVia;
    }

    /**
     * Accepts "host", "host:port", "1.2.3.4:8080" or "[::1]:8080"; the port defaults to the base
     * URL's port. Returns "host:port" or ''.
     *
     * @throws \InvalidArgumentException
     */
    public static function normalizeConnectVia(string $value, int $defaultPort): string
    {
        $value = strtolower(trim($value));

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^(\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::([0-9]{1,5}))?$/', $value, $m)) {
            throw new \InvalidArgumentException('Connect via must be host or host:port');
        }

        $host = $m[1];
        $port = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $defaultPort;
        $bare = trim($host, '[]');

        $isIp   = filter_var($bare, FILTER_VALIDATE_IP) !== false;
        $isName = !str_starts_with($host, '[')
            && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) === 1;

        if ((!$isIp && !$isName) || $port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Connect via must be host or host:port');
        }

        return $host . ':' . $port;
    }

    /**
     * Returns scheme://host[:port][/context-path] with no trailing slash and no /api/v3 suffix.
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url   = trim($url);
        $parts = parse_url($url);

        if ($url === '' || $parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            throw new \InvalidArgumentException('Base URL must be an absolute http(s) URL');
        }

        $scheme = strtolower($parts['scheme']);

        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Base URL must use http or https');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Base URL must not contain credentials, a query or a fragment');
        }

        $path = rtrim($parts['path'] ?? '', '/');
        $path = preg_replace('#/api/v3$#i', '', $path) ?? '';

        if ($path !== '' && !preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $path)) {
            throw new \InvalidArgumentException('Base URL path contains invalid characters');
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $scheme . '://' . strtolower($parts['host']) . $port . $path;
    }

    public function isHttps(): bool
    {
        return str_starts_with($this->baseUrl, 'https://');
    }
}
