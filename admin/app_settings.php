<?php
$title = "Pengaturan Aplikasi";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$appName = get_app_setting('app_name','Absensi Sekolah');
$appLogo = get_app_setting('app_logo','');
$footerText = get_app_setting('footer_text','');
$marqueeEnabled = get_app_setting('marquee_enabled','0');
$marqueeText = get_app_setting('marquee_text','');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $appName = trim($_POST["app_name"] ?? "");
  if ($appName === "") $appName = "Absensi Sekolah";

  $footerText = trim($_POST["footer_text"] ?? "");
  $marqueeEnabled = isset($_POST["marquee_enabled"]) ? "1" : "0";
  $marqueeText = trim($_POST["marquee_text"] ?? "");

  // upload logo (optional)
  if (!empty($_FILES["app_logo"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["app_logo"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
      flash_set("danger","Format logo harus jpg/png/webp.");
      redirect("/admin/app_settings.php");
    }
    $dir = __DIR__ . "/../uploads/app/";
    ensure_upload_dir($dir);
    $fname = "logo." . $ext;
    if (!move_uploaded_file($_FILES["app_logo"]["tmp_name"], $dir . $fname)) {
      flash_set("danger","Gagal upload logo.");
      redirect("/admin/app_settings.php");
    }
    $appLogo = "uploads/app/" . $fname;
    set_app_setting("app_logo", $appLogo);
  }

  set_app_setting("app_name", $appName);
  set_app_setting("footer_text", $footerText);
  set_app_setting("marquee_enabled", $marqueeEnabled);
  set_app_setting("marquee_text", $marqueeText);

  flash_set("success","Pengaturan aplikasi berhasil disimpan.");
  redirect("/admin/app_settings.php");
}

require_once __DIR__ . "/../includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="card card-soft p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-semibold fs-5"><i class="bi bi-sliders me-1"></i>Pengaturan Aplikasi</div>
        <a class="btn btn-outline-secondary" href="dashboard.php"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
      </div>
      <hr>

      <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-12">
          <label class="form-label">Nama Aplikasi</label>
          <input class="form-control" name="app_name" value="<?= e($appName) ?>" required>
          <div class="text-secondary small mt-1">Akan tampil di navbar, login, dan judul halaman.</div>
        </div>

        <div class="col-12">
          <label class="form-label">Logo Aplikasi (opsional)</label>
          <input class="form-control" type="file" name="app_logo" accept="image/*">
          <?php if (!empty($appLogo) && file_exists(__DIR__ . "/../" . $appLogo)): ?>
            <div class="mt-2">
              <img src="<?= $BASE_URL . "/" . e($appLogo) ?>" alt="logo" style="height:48px;border-radius:12px;border:1px solid rgba(10,40,80,.15);">
            </div>
          <?php endif; ?>
        </div>

        <div class="col-12">
          <label class="form-label">Running Text Pengumuman</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="marq" name="marquee_enabled" <?= $marqueeEnabled==='1'?'checked':'' ?>>
            <label class="form-check-label" for="marq">Aktifkan</label>
          </div>
          <textarea class="form-control mt-2" rows="2" name="marquee_text" placeholder="Isi pengumuman..."><?= e($marqueeText) ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Footer</label>
          <input class="form-control" name="footer_text" value="<?= e($footerText) ?>" placeholder="Contoh: © 2026 SMK Contoh - Semua hak dilindungi">
          <div class="text-secondary small mt-1">Kosongkan untuk default otomatis © Tahun + Nama Aplikasi.</div>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Pengaturan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
