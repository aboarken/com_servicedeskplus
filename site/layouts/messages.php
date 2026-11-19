<?php

/**
 * Messages waiting for the user (e.g. the result of an approval decision), shown inside the page,
 * above its title, in the style of the "Updating…" note. They are taken from Joomla's message
 * queue, so the site template does not show them a second time.
 *
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$tones = [
    'message' => 'success',
    'success' => 'success',
    'notice'  => 'info',
    'info'    => 'info',
    'warning' => 'warning',
    'error'   => 'error',
    'danger'  => 'error',
];

foreach (Factory::getApplication()->getMessageQueue(true) as $message) :
    $text = trim(strip_tags((string) ($message['message'] ?? '')));

    if ($text === '') {
        continue;
    }

    $tone = $tones[strtolower((string) ($message['type'] ?? 'message'))] ?? 'info';
    ?>
    <p class="sdp-refresh-note sdp-message sdp-message--<?php echo $tone; ?>" role="<?php echo $tone === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endforeach;
