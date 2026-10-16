<?php
/**
 * Toast notifications — one small box in the corner that fades away.
 * Included once by footer.php. Queue a toast from any page with toast() (see config/app.php),
 * or from JavaScript with  showToast({ title: 'Saved', type: 'success' }).
 */
$toasts = toasts_pull();

$icons = [
    'success' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    'error'   => '<path d="M12 6.5v7M12 17.5h.01"/>',
    'info'    => '<path d="M12 10.5v7M12 6.5h.01"/>',
];
$timeouts = ['success' => 5000, 'info' => 6000, 'error' => 8000];   // milliseconds on screen
?>
<div class="toast-region" id="toast-region">
    <?php foreach ($toasts as $t): ?>
    <div class="toast toast--<?= e($t['type']) ?>" role="<?= $t['type'] === 'error' ? 'alert' : 'status' ?>" data-toast data-timeout="<?= $timeouts[$t['type']] ?>">
        <span class="toast__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><?= $icons[$t['type']] ?></svg>
        </span>
        <div class="toast__body">
            <p class="toast__title"><?= e($t['title']) ?></p>
            <?php if ($t['text'] !== ''): ?><p class="toast__text"><?= e($t['text']) ?></p><?php endif; ?>
        </div>
        <?php if ($t['action_url'] !== '' && $t['action_label'] !== ''): ?>
            <a class="toast__action" href="<?= e($t['action_url']) ?>"><?= e($t['action_label']) ?></a>
        <?php endif; ?>
        <button type="button" class="toast__close" aria-label="Dismiss notification" data-toast-close>&times;</button>
    </div>
    <?php endforeach; ?>
</div>
