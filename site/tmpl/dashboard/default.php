<?php

/**
 * @license GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \SdpPortal\Component\ServiceDeskPlus\Site\View\Dashboard\HtmlView $this */
?>
<div class="com-servicedeskplus sdp-dashboard">
    <?php if ($this->showPageHeading) : ?>
        <h1 class="h2 mb-3"><?php echo $this->escape($this->pageHeading); ?></h1>
    <?php endif; ?>

    <?php foreach ($this->sections as $section) : ?>
        <section class="mb-4">
            <h2 class="h4"><?php echo Text::_($section['title']); ?></h2>
            <?php if ($section['error'] !== '') : ?>
                <div class="alert alert-danger" role="alert"><?php echo $this->escape($section['error']); ?></div>
            <?php else : ?>
                <div class="row g-3">
                    <?php foreach ($section['cards'] as $card) : ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card h-100 sdp-stat">
                                <div class="card-body">
                                    <div class="fs-2 fw-semibold"><?php echo $card['value'] !== null ? (int) $card['value'] : '—'; ?></div>
                                    <a class="stretched-link text-reset text-decoration-none" href="<?php echo $card['url']; ?>"><?php echo $this->escape($card['label']); ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
