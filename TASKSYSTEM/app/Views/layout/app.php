<?php $config = require __DIR__ . '/../../Config/Config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="<?= $config['app_url'] ?>/assets/css/bootstrap.min.css">
</head>
<body>
    <?php require $contentView; ?>
</body>
</html><script src="<?= $config['app_url'] ?>/assets/js/bootstrap.min.js"></script>