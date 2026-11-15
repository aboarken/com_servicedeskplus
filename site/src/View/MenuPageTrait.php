<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Page heading and browser title for pages that are reached from a menu item
 * (My Requests, New Request, My Loans, My Approvals, Announcements).
 *
 * When the active menu item points to this view, its settings are used, as in Joomla core:
 *  - heading: the menu item's "Page Heading", otherwise the menu item title;
 *  - browser title: the menu item's "Browser Page Title", otherwise the menu item title;
 *  - "Show Page Heading" set to No hides the heading (Use Global and Yes show it).
 * Without such a menu item the component's own title is used.
 *
 * Requires Joomla\CMS\MVC\View\HtmlView (setDocumentTitle()).
 */
trait MenuPageTrait
{
    public string $pageHeading = '';

    public bool $showPageHeading = true;

    protected function prepareMenuPage(string $view, string $defaultTitleKey): void
    {
        $default = Text::_($defaultTitleKey);
        $heading = $default;
        $title   = $default;
        $show    = true;

        $item = Factory::getApplication()->getMenu()->getActive();

        if (
            $item !== null
            && ($item->query['option'] ?? '') === 'com_servicedeskplus'
            && ($item->query['view'] ?? '') === $view
        ) {
            $params    = $item->getParams();
            $menuTitle = trim((string) $item->title);

            if ($menuTitle !== '') {
                $heading = $menuTitle;
                $title   = $menuTitle;
            }

            $heading = trim((string) $params->get('page_heading', '')) ?: $heading;
            $title   = trim((string) $params->get('page_title', '')) ?: $title;
            $show    = (string) $params->get('show_page_heading', '') !== '0';
        }

        $this->pageHeading     = $heading;
        $this->showPageHeading = $show;

        // Adds the site name before/after the title when the Global Configuration asks for it.
        $this->setDocumentTitle($title);
    }
}
