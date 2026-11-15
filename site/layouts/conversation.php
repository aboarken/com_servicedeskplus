<?php

/**
 * Conversation of a request (e-mails and notes visible to the requester), oldest first.
 *
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use SdpPortal\Component\ServiceDeskPlus\Administrator\Requests\ConversationEntry;

/** @var array{entries: list<ConversationEntry>} $displayData */
$entries = $displayData['entries'] ?? [];
$esc     = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$date    = static fn (?int $ts): string => $ts ? HTMLHelper::_('date', gmdate('Y-m-d H:i:s', $ts), Text::_('DATE_FORMAT_LC2')) : '';
?>
<h2 class="h4 mt-4"><?php echo Text::_('COM_SERVICEDESKPLUS_CONVERSATION'); ?></h2>
<?php if ($entries === []) : ?>
    <p class="text-muted"><?php echo Text::_('COM_SERVICEDESKPLUS_CONVERSATION_NONE'); ?></p>
<?php else : ?>
    <div class="sdp-conversation">
        <?php foreach ($entries as $entry) : ?>
            <div class="card mb-3">
                <div class="card-header small">
                    <strong><?php echo $esc($entry->from !== '' ? $entry->from : '—'); ?></strong>
                    <span class="text-muted">
                        · <?php echo Text::_($entry->type === ConversationEntry::TYPE_NOTE ? 'COM_SERVICEDESKPLUS_CONVERSATION_NOTE' : 'COM_SERVICEDESKPLUS_CONVERSATION_EMAIL'); ?>
                        <?php if ($entry->time) : ?>· <?php echo $date($entry->time); ?><?php endif; ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($entry->subject !== '') : ?>
                        <p class="fw-semibold mb-2"><?php echo $esc($entry->subject); ?></p>
                    <?php endif; ?>
                    <div class="sdp-text"><?php echo $entry->text !== '' ? nl2br($esc($entry->text)) : '—'; ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
