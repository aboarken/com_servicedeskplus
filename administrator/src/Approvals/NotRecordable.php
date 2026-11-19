<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Approvals;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * ServiceDesk Plus refused the direct decision and did not let the API technician be added to
 * the level either: the record is closed (REASON_CLOSED), or the API technician's role may not
 * change approvals in that module (REASON_PERMISSION, e.g. only "View" for Purchase Orders), or
 * ServiceDesk Plus did not apply the decision to the API technician's entry (REASON_NOT_APPLIED).
 */
final class NotRecordable extends \RuntimeException
{
    public const REASON_CLOSED     = 'closed';
    public const REASON_PERMISSION = 'permission';

    /** ServiceDesk Plus answered OK but the API technician's entry was not decided (e.g. its
     *  approval e-mail was never sent, so it stayed "To Be Sent"). Nothing was changed. */
    public const REASON_NOT_APPLIED = 'not_applied';

    public function __construct(string $message, public readonly string $reason = self::REASON_PERMISSION, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
