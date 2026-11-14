<?php

/**
 * @license GNU General Public License version 2 or later
 */

namespace SdpPortal\Component\ServiceDeskPlus\Site\View\Requestform;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use SdpPortal\Component\ServiceDeskPlus\Site\Model\RequestformModel;

final class HtmlView extends BaseHtmlView
{
    public ?string $createdId = null;

    /** @var array{subject: string, description: string} */
    public array $input = ['subject' => '', 'description' => ''];

    public string $submissionToken = '';

    public function display($tpl = null): void
    {
        /** @var RequestformModel $model */
        $model = $this->getModel();

        $this->createdId       = $model->takeCreatedId();
        $this->input           = $model->takeInput();
        $this->submissionToken = $model->issueSubmissionToken();

        $document = $this->getDocument();
        $document->setTitle(Text::_('COM_SERVICEDESKPLUS_REQUESTFORM_TITLE'));
        $document->setMetaData('robots', 'noindex, nofollow');

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_servicedeskplus');
        $wa->useScript('com_servicedeskplus.submit-once');

        parent::display($tpl);
    }
}
