<!DOCTYPE html>
<html lang="<?= current_language() ?>" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e(isset($title) ? $title . ' | ' . $_ENV['APP_NAME'] : $_ENV['APP_NAME']) ?></title>
    <link rel="icon" type="image/png" href="/assets/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg" />
    <link rel="shortcut icon" href="/assets/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="<?= $this->e($title ?? $_ENV['APP_NAME']) ?>" />
    <link rel="manifest" href="/assets/site.webmanifest" />
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $this->e($title ?? $_ENV['APP_NAME']) ?>">
    <meta property="og:url" content="<?= $_ENV['APP_URL'] ?>">
    <meta property="og:image" content="/assets/ogimage.png">
    <meta property="og:description" content="<?= __('meta.description') ?>">
    <link href="/assets/css/lerama.min.css" rel="stylesheet">
</head>

<body class="bg-light min-vh-100 d-flex flex-column">
    <?= $this->section('content') ?>
    
</body>

</html>