<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? env('APP_NAME', 'CharityBridge')) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <?php if (!empty($css)): ?>
        <?php foreach ((array)$css as $sheet): ?>
            <link rel="stylesheet" href="<?= asset('css/' . $sheet) ?>">
        <?php endforeach ?>
    <?php endif ?>
</head>
<body>
    <div class="container">
        <?php partial('flash') ?>
        <?= $slot ?>
    </div>
</body>
</html>
