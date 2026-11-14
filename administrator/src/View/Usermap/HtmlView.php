<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Administrator\View\Usermap;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Identity\UserMapping;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Model\UsermapModel;

final class HtmlView extends BaseHtmlView
{
    public Form $form;

    public ?UserMapping $mapping = null;

    public function display($tpl = null): void
    {
        /** @var UsermapModel $model */
        $model = $this->getModel();

        $this->form    = $model->getForm();
        $this->mapping = $model->getMapping();

        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        ToolbarHelper::title(Text::_('COM_SERVICEDESKPLUS_MAPPING_EDIT_TITLE'), 'users');

        $toolbar = $this->getDocument()->getToolbar();
        $toolbar->save('usermap.save');
        $toolbar->cancel('usermap.cancel', 'JTOOLBAR_CANCEL');

        parent::display($tpl);
    }
}
