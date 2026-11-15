<?php

/**
 * Links between the portal pages (My Requests, New Request, My Approvals, My Loans,
 * Announcements), shown at the top of each of them. Only pages the user may open are listed;
 * a page's label is the title of its site menu item when there is one.
 *
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var array{active: string} $displayData */
$active = (string) ($displayData['active'] ?? '');
$app    = Factory::getApplication();
$user   = $app->getIdentity();
$menu   = $app->getMenu();
$esc    = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** view => [permission, default label] */
$pages = [
    'requests'      => ['sdp.request.view', 'COM_SERVICEDESKPLUS_NAV_REQUESTS'],
    'requestform'   => ['sdp.request.create', 'COM_SERVICEDESKPLUS_NAV_REQUESTFORM'],
    'approvals'     => ['sdp.approval.view', 'COM_SERVICEDESKPLUS_NAV_APPROVALS'],
    'assets'        => ['sdp.asset.view', 'COM_SERVICEDESKPLUS_NAV_ASSETS'],
    'announcements' => ['sdp.announcement.view', 'COM_SERVICEDESKPLUS_NAV_ANNOUNCEMENTS'],
];
$links = [];

foreach ($pages as $view => [$permission, $labelKey]) {
    if ($user === null || !$user->authorise($permission, 'com_servicedeskplus')) {
        continue;
    }

    $link  = 'index.php?option=com_servicedeskplus&view=' . $view;
    $item  = $menu->getItems('link', $link, true);
    $label = \is_object($item) && trim((string) $item->title) !== '' ? trim((string) $item->title) : Text::_($labelKey);

    $links[$view] = [$label, Route::_($link)];
}

if (\count($links) < 2) {
    return;
}
?>
<nav class="sdp-nav mb-3" aria-label="<?php echo $esc(Text::_('COM_SERVICEDESKPLUS_NAV_LABEL')); ?>">
    <ul class="nav nav-pills flex-wrap gap-1">
        <?php foreach ($links as $view => [$label, $url]) : ?>
            <li class="nav-item">
                <?php if ($view === $active) : ?>
                    <a class="nav-link active" aria-current="page" href="<?php echo $url; ?>"><?php echo $esc($label); ?></a>
                <?php else : ?>
                    <a class="nav-link" href="<?php echo $url; ?>"><?php echo $esc($label); ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
