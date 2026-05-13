<?php
require_once __DIR__ . "/functions.php";
$u = current_user();
$appName = get_app_setting('app_name','Absensi Sekolah');
$appLogo = get_app_setting('app_logo','');
$appLogoPath = ltrim((string)$appLogo, '/');
$marqueeEnabled = get_app_setting('marquee_enabled','0');
$marqueeText = get_app_setting('marquee_text','');
$f = flash_get();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($title ?? $appName) . " - " . $appName) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= $BASE_URL ?>/assets/css/style.css" rel="stylesheet">
  <link href="<?= $BASE_URL ?>/assets/css/theme.css?v=1" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="<?= $BASE_URL ?>/index.php">
  <?php if (!empty($appLogoPath)): ?>
    <img class="brand-logo" src="<?= $BASE_URL . "/" . e($appLogoPath) ?>" alt="logo" onerror="this.style.display='none'">
  <?php else: ?>
    <i class="bi bi-check2-square"></i>
  <?php endif; ?>
  <span><?= e($appName) ?></span>
</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if ($u): ?>
        <span id="clockWIB" class="text-white small fw-semibold me-2"></span>
        <span class="text-white small d-none d-md-inline">Halo, <?= e($u["full_name"]) ?> (<?= e(role_label($u["role"])) ?>)</span>
        <a class="btn btn-sm btn-outline-light" href="<?= $BASE_URL ?>/profile.php"><i class="bi bi-person-circle me-1"></i>Profil</a>
        <a class="btn btn-sm btn-light" href="<?= $BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
      <?php else: ?>
        <span id="clockWIB" class="text-white small fw-semibold me-2"></span>
        <a class="btn btn-sm btn-light" href="<?= $BASE_URL ?>/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<?php if ($marqueeEnabled==='1' && trim((string)$marqueeText) !== ''): ?>
<div class="marquee-wrap">
  <div class="container py-2">
    <div class="marquee"><span><i class="bi bi-megaphone-fill me-2"></i><?= e($marqueeText) ?></span></div>
  </div>
</div>
<?php endif; ?>

<main class="container py-3">
  <?php if ($f): ?>
    <div class="alert alert-<?= e($f["type"]) ?>"><?= e($f["msg"]) ?></div>
  <?php endif; ?>
