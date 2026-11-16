<?php

/**
 * Conversation of a request (e-mails and notes visible to the requester), oldest first, as a
 * timeline in a panel.
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
<section class="sdp-panel sdp-conversation">
    <div class="sdp-panel-head">
        <h2 class="sdp-panel-title"><?php echo Text::_('COM_SERVICEDESKPLUS_CONVERSATION'); ?></h2>
        <?php if ($entries !== []) : ?>
            <span class="sdp-panel-count"><?php echo \count($entries); ?></span>
        <?php endif; ?>
    </div>
    <?php if ($entries === []) : ?>
        <p class="sdp-panel-body sdp-empty"><?php echo Text::_('COM_SERVICEDESKPLUS_CONVERSATION_NONE'); ?></p>
    <?php else : ?>
        <ol class="sdp-timeline">
            <?php foreach ($entries as $entry) :
                $isNote = $entry->type === ConversationEntry::TYPE_NOTE;
                ?>
                <li class="sdp-tone-<?php echo $isNote ? 'amber' : 'blue'; ?>">
                    <div class="sdp-timeline-head">
                        <strong><?php echo $esc($entry->from !== '' ? $entry->from : '—'); ?></strong>
                        <span class="sdp-badge sdp-tone-<?php echo $isNote ? 'amber' : 'blue'; ?>"><?php echo Text::_($isNote ? 'COM_SERVICEDESKPLUS_CONVERSATION_NOTE' : 'COM_SERVICEDESKPLUS_CONVERSATION_EMAIL'); ?></span>
                        <?php if ($entry->time) : ?>
                            <span class="sdp-timeline-time"><?php echo $date($entry->time); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($entry->subject !== '') : ?>
                        <p class="sdp-timeline-subject"><?php echo $esc($entry->subject); ?></p>
                    <?php endif; ?>
                    <div class="sdp-text sdp-prose"><?php echo $entry->text !== '' ? nl2br($esc($entry->text)) : '—'; ?></div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
