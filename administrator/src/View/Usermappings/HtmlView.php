<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\View\Usermappings;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Model\UsermappingsModel;

final class HtmlView extends BaseHtmlView
{
    /** @var list<object> */
    public array $items = [];

    public Pagination $pagination;

    public Registry $state;

    public ?Form $filterForm = null;

    /** @var array<string, mixed> */
    public array $activeFilters = [];

    public function display($tpl = null): void
    {
        /** @var UsermappingsModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems() ?: [];
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm() ?: null;
        $this->activeFilters = $model->getActiveFilters();

        $this->addToolbar();

        $assets = $this->getDocument()->getWebAssetManager();
        $assets->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $assets->useStyle('com_servicedeskplus.admin');

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_SERVICEDESKPLUS_MAPPINGS_TITLE'), 'users');

        $toolbar = $this->getDocument()->getToolbar();

        $toolbar->addNew('usermap.add');

        $toolbar->standardButton('confirm', 'COM_SERVICEDESKPLUS_MAPPING_CONFIRM')
            ->task('usermap.confirm')
            ->icon('icon-check')
            ->listCheck(true);

        $toolbar->standardButton('suspend', 'COM_SERVICEDESKPLUS_MAPPING_SUSPEND')
            ->task('usermap.suspend')
            ->icon('icon-lock')
            ->listCheck(true);

        $toolbar->standardButton('rematch', 'COM_SERVICEDESKPLUS_MAPPING_REMATCH')
            ->task('usermap.rematch')
            ->icon('icon-refresh')
            ->listCheck(true);

        // Leaves the component for the administrator dashboard; unsaved changes are discarded.
        $toolbar->linkButton('close', 'JTOOLBAR_CLOSE')
            ->url('index.php')
            ->icon('icon-times');

        $toolbar->preferences('com_servicedeskplus');
    }
}
