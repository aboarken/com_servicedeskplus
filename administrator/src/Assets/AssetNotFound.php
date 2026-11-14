<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\Assets;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The asset does not exist, is not assigned to the user, or is not a loan asset.
 */
final class AssetNotFound extends \RuntimeException
{
}
