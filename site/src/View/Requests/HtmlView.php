<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Requests;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Api\SdpException;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestListResult;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\RequestQuery;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\RequestsModel;
use SdpPortal\Component\ServiceDeskPlus\Site\View\MenuPageTrait;

final class HtmlView extends BaseHtmlView
{
    use MenuPageTrait;

    public ?RequestListResult $result = null;

    public RequestQuery $query;

    /** @var list<string> */
    public array $statuses = [];

    public string $error = '';

    public bool $canCreate = false;

    public function display($tpl = null): void
    {
        /** @var RequestsModel $model */
        $model = $this->getModel();

        $this->query     = $model->getQuery();
        $this->statuses  = $model->getStatuses();
        $this->canCreate = Factory::getApplication()->getIdentity()->authorise('sdp.request.create', 'com_servicedeskplus');

        try {
            $this->result = $model->getResult();
        } catch (SdpException $e) {
            $this->error = Text::_($e->getLanguageKey());
        }

        $this->prepareMenuPage('requests', 'COM_SERVICEDESKPLUS_REQUESTS_TITLE');
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');
        $this->getDocument()->getWebAssetManager()->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $this->getDocument()->getWebAssetManager()->useStyle('com_servicedeskplus.portal')->useScript('com_servicedeskplus.filters');

        parent::display($tpl);
    }

    /**
     * Link to this list with the current filters and a different page.
     */
    public function pageUrl(int $page): string
    {
        $q = $this->query;

        $params = array_filter([
            'search' => $q->search,
            'type'   => $q->type,
            'sort'   => $q->sort . '_' . $q->direction,
            'page'   => $page > 1 ? $page : null,
        ], static fn ($v) => $v !== null && $v !== '');

        // Always keep the status ("all" for all statuses), so paging does not fall back to the
        // default "Open" filter.
        $params['status'] = $q->status !== '' ? $q->status : RequestQuery::ALL_STATUSES;

        return Route::_('index.php?option=com_servicedeskplus&view=requests&' . http_build_query($params));
    }
}
