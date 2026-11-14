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
 * the level either, for example because the request, change, release or purchase order is
 * closed. The approval can only be handled in ServiceDesk Plus itself.
 */
final class NotRecordable extends \RuntimeException
{
}
