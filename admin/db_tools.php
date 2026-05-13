<?php
$title = "Backup & Restore Database";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/db_tools.php";
require_login();
require_role("ADMIN");

// ACTION: download backup
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
  $gzip = isset($_GET['gzip']) && $_GET['gzip'] === '1';
  $includeData = !isset($_GET['schema_only']);
  $base = 'absensi_sekolah_' . date('Ymd_His');
  dbtools_dump($mysqli, [
    'gzip' => $gzip,
    'include_data' => $includeData,
    'filename_base' => $base,
  ]);
  exit;
}

// ACTION: restore
$restoreResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
  $confirm = trim((string)($_POST['confirm'] ?? ''));
  if (strtoupper($confirm) !== 'RESTORE') {
    $restoreResult = ['ok'=>false, 'error'=>'Konfirmasi salah. Ketik RESTORE untuk melanjutkan.'];
  } elseif (empty($_FILES['sql_file']['name'])) {
    $restoreResult = ['ok'=>false, 'error'=>'Silakan pilih file .sql atau .sql.gz terlebih dahulu.'];
  } else {
    $name = (string)$_FILES['sql_file']['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['sql','gz'], true)) {
      $restoreResult = ['ok'=>false, 'error'=>'Format file harus .sql atau .sql.gz'];
    } elseif (!is_uploaded_file($_FILES['sql_file']['tmp_name'])) {
      $restoreResult = ['ok'=>false, 'error'=>'Upload file gagal. Coba ulangi.'];
    } else {
      // batas ukuran (soft) 50MB
      $size = (int)($_FILES['sql_file']['size'] ?? 0);
      if ($size > 50 * 1024 * 1024) {
        $restoreResult = ['ok'=>false, 'error'=>'File terlalu besar (>50MB). Naikkan upload_max_filesize/post_max_size atau kompres ke .gz.'];
      } else {
        try {
          mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
          $restoreResult = dbtools_import($mysqli, $_FILES['sql_file']['tmp_name']);
          if ($restoreResult['ok']) {
            flash_set('success', 'Restore berhasil. Statements dieksekusi: ' . (int)($restoreResult['executed'] ?? 0));
            redirect('/admin/db_tools.php');
          }
        } catch (Throwable $t) {
          $restoreResult = ['ok'=>false, 'error'=>$t->getMessage()];
        }
      }
    }
  }
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="fw-semibold fs-5"><i class="bi bi-hdd-network me-1"></i>Backup & Restore Database</div>
    <div class="text-secondary small">Gunakan menu ini untuk download backup (.sql/.sql.gz) dan restore kembali.</div>
  </div>
  <a class="btn btn-outline-secondary" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php if ($restoreResult && !$restoreResult['ok']): ?>
  <div class="alert alert-danger">
    <div class="fw-semibold">Restore gagal</div>
    <div class="small"><?= e($restoreResult['error'] ?? 'Unknown error') ?></div>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="card card-soft p-3">
      <div class="fw-semibold mb-2"><i class="bi bi-download me-1"></i>Backup</div>
      <div class="text-secondary small mb-3">Backup berisi <b>schema + data</b> (DROP + CREATE + INSERT).</div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="db_tools.php?action=backup"><i class="bi bi-filetype-sql me-1"></i>Download .SQL</a>
        <a class="btn btn-outline-primary" href="db_tools.php?action=backup&gzip=1"><i class="bi bi-file-zip me-1"></i>Download .SQL.GZ</a>
      </div>
      <div class="text-secondary small mt-3">
        Tips: untuk database besar, pilih <b>.sql.gz</b> agar lebih ringan.
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card card-soft p-3">
      <div class="fw-semibold mb-2"><i class="bi bi-upload me-1"></i>Restore</div>
      <div class="alert alert-warning small mb-3">
        <b>Perhatian:</b> restore akan menimpa data (DROP/CREATE). Pastikan sudah backup.
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="restore">
        <div class="mb-2">
          <label class="form-label">File backup (.sql / .sql.gz)</label>
          <input class="form-control" type="file" name="sql_file" accept=".sql,.gz" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Konfirmasi</label>
          <input class="form-control" name="confirm" placeholder="Ketik RESTORE" required>
          <div class="form-text">Ketik <b>RESTORE</b> (huruf besar) untuk melanjutkan.</div>
        </div>
        <button class="btn btn-danger" type="submit"><i class="bi bi-arrow-clockwise me-1"></i>Restore Database</button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
