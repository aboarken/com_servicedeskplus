<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\View\Endpoints;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Controller\EndpointsController;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Discovery\EndpointCatalog;

/**
 * Find Endpoints. Shows the known endpoints with the last availability check, the last endpoint
 * check and the last Diagnostics report, all kept in the administrator's session only.
 */
final class HtmlView extends BaseHtmlView
{
    /** @var array{results: array<string, array{ok: bool, kind: string, http: int, total: ?int, key: string}>, at: int}|null */
    public ?array $catalog = null;

    /** @var array<string, mixed>|null */
    public ?array $check = null;

    /** @var array{report: string, email: string}|null */
    public ?array $diagnostics = null;

    public string $filter = '';

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        $catalog           = $app->getUserState(EndpointsController::STATE_CATALOG);
        $check             = $app->getUserState(EndpointsController::STATE_CHECK);
        $diag              = $app->getUserState(EndpointsController::STATE_DIAG);
        $this->catalog     = \is_array($catalog) ? $catalog : null;
        $this->check       = \is_array($check) ? $check : null;
        $this->diagnostics = \is_array($diag) ? $diag : null;
        $this->filter      = mb_substr(trim($app->getInput()->getString('filter', '')), 0, 60);

        ToolbarHelper::title(Text::_('COM_SERVICEDESKPLUS_ENDPOINTS_TITLE'), 'search');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->linkButton('settings', 'COM_SERVICEDESKPLUS_SUBMENU_SETTINGS')
            ->url('index.php?option=com_servicedeskplus&view=integrationsettings')
            ->icon('icon-cog');
        $toolbar->linkButton('close', 'JTOOLBAR_CLOSE')
            ->url('index.php')
            ->icon('icon-times');
        $toolbar->preferences('com_servicedeskplus');

        $assets = $this->getDocument()->getWebAssetManager();
        $assets->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $assets->useStyle('com_servicedeskplus.admin');
        $assets->useScript('com_servicedeskplus.admin');

        parent::display($tpl);
    }

    /**
     * Known endpoints, filtered by text; the portal's own first.
     *
     * @return list<array{path: string, summary: string, portal: string}>
     */
    public function endpoints(): array
    {
        $needle = mb_strtolower($this->filter);

        return array_values(array_filter(
            EndpointCatalog::all(),
            static fn (array $e): bool => $needle === '' || str_contains(mb_strtolower($e['path'] . ' ' . $e['summary'] . ' ' . $e['portal']), $needle)
        ));
    }

    /**
     * @return array{ok: bool, kind: string, http: int, total: ?int, key: string}|null
     */
    public function resultFor(string $path): ?array
    {
        $result = $this->catalog['results'][$path] ?? null;

        return \is_array($result) ? $result : null;
    }
}
