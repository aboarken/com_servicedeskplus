<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Announcements;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


/**
 * The announcement does not exist or is not shown to the user. Both cases look the same to the user.
 */
final class AnnouncementNotFound extends \RuntimeException
{
}
