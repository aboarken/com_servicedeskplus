<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Unmatched;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Shown when the user has no usable SDP mapping. Displays one message and nothing else.
 */
final class HtmlView extends BaseHtmlView
{
    public function display($tpl = null): void
    {
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        parent::display($tpl);
    }
}
