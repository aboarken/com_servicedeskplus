<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Api;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Error raised by SdpClient. The "kind" is stable and drives the user-facing message;
 * the SDP message is kept for logging and never contains a key.
 */
final class SdpException extends \RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';
    public const NETWORK        = 'network';
    public const AUTH           = 'auth';
    public const PERMISSION     = 'permission';
    public const NOT_FOUND      = 'not_found';
    public const VALIDATION     = 'validation';
    public const RATE_LIMITED   = 'rate_limited';
    public const LICENSE        = 'license';
    public const SERVER         = 'server';
    public const BAD_RESPONSE   = 'bad_response';

    public function __construct(
        private readonly string $kind,
        string $message,
        private readonly int $httpStatus = 0,
        private readonly ?int $sdpCode = null,
        private readonly string $sdpMessage = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Map an SDP response to an exception. SDP message codes take precedence over HTTP codes.
     */
    public static function fromResponse(int $httpStatus, ?int $sdpCode, string $sdpMessage): self
    {
        $kind = match (true) {
            $sdpCode === 4022, $httpStatus === 401 => self::AUTH,
            $sdpCode === 4002, $httpStatus === 403 => self::PERMISSION,
            $sdpCode === 4007, $httpStatus === 404 => self::NOT_FOUND,
            $sdpCode === 4015, $httpStatus === 429 => self::RATE_LIMITED,
            $sdpCode === 7001                      => self::LICENSE,
            $sdpCode === 4004, $httpStatus >= 500  => self::SERVER,
            default                                => self::VALIDATION,
        };

        return new self($kind, 'ServiceDesk Plus returned an error (' . $kind . ')', $httpStatus, $sdpCode, $sdpMessage);
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getSdpCode(): ?int
    {
        return $this->sdpCode;
    }

    /**
     * Message text from SDP, sanitised and truncated. Safe to log; show to users only for VALIDATION.
     */
    public function getSdpMessage(): string
    {
        return $this->sdpMessage;
    }

    /**
     * Language key for the user-facing message.
     */
    public function getLanguageKey(): string
    {
        return 'COM_SERVICEDESKPLUS_ERROR_' . strtoupper($this->kind);
    }
}
