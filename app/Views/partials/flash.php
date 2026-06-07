<?php
$flash = \App\Core\Flash::consume();
if ($flash !== null):
    $type = $flash['type'] ?? 'info';
    $cls  = $type === 'success' ? 'success' : 'errors';
?>
    <div class="<?= e($cls) ?>">
        <p><?= e($flash['message'] ?? '') ?></p>
    </div>
<?php endif ?>
