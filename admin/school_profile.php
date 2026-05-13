<?php
$title = "Identitas Sekolah";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

// Migrasi aman: pastikan kolom identitas sekolah lengkap (tanpa INFORMATION_SCHEMA)
$cols = [
  "school_address" => "ALTER TABLE school_profile ADD COLUMN school_address TEXT NULL AFTER school_name",
  "school_phone"   => "ALTER TABLE school_profile ADD COLUMN school_phone VARCHAR(100) NULL AFTER school_address",
  "school_email"   => "ALTER TABLE school_profile ADD COLUMN school_email VARCHAR(120) NULL AFTER school_phone",
  "city"           => "ALTER TABLE school_profile ADD COLUMN city VARCHAR(100) NULL AFTER school_email"
];
foreach ($cols as $col => $sqlAlter) {
  $chk = $mysqli->query("SHOW COLUMNS FROM school_profile LIKE '$col'");
  if ($chk && $chk->num_rows == 0) { @$mysqli->query($sqlAlter); }
}

// Pastikan ada row id=1
$mysqli->query("INSERT INTO school_profile (id, school_name) VALUES (1,'Sekolah') ON DUPLICATE KEY UPDATE school_name=school_name");

$school = $mysqli->query("SELECT * FROM school_profile WHERE id=1")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name  = trim($_POST["school_name"] ?? "");
  $addr  = trim($_POST["school_address"] ?? "");
  $phone = trim($_POST["school_phone"] ?? "");
  $email = trim($_POST["school_email"] ?? "");
  $city  = trim($_POST["city"] ?? "");
  $lat   = (float)($_POST["geo_lat"] ?? 0);
  $lng   = (float)($_POST["geo_lng"] ?? 0);
  $rad   = (int)($_POST["geo_radius_m"] ?? 150);

  $logo_path = $school["logo_path"] ?? null;

  // Upload logo (opsional)
  if (!empty($_FILES["logo"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
      flash_set("warning", "Logo harus jpg/png/webp.");
      redirect("/admin/school_profile.php");
    }
    $dir = __DIR__ . "/../uploads/app";
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $filename = "school_logo_" . time() . "." . $ext;
    $dest = $dir . "/" . $filename;
    if (!move_uploaded_file($_FILES["logo"]["tmp_name"], $dest)) {
      flash_set("warning", "Gagal upload logo.");
      redirect("/admin/school_profile.php");
    }
    $logo_path = "uploads/app/" . $filename;
  }

  $stmt = $mysqli->prepare("UPDATE school_profile 
    SET school_name=?, school_address=?, school_phone=?, school_email=?, city=?, logo_path=?, geo_lat=?, geo_lng=?, geo_radius_m=?
    WHERE id=1");
  if (!$stmt) {
    flash_set("warning", "Query error: " . $mysqli->error);
    redirect("/admin/school_profile.php");
  }
  $stmt->bind_param("ssssssddi", $name, $addr, $phone, $email, $city, $logo_path, $lat, $lng, $rad);
  $stmt->execute();

  // Kompatibilitas versi lama (jika ada kolom lama)
  $c1 = $mysqli->query("SHOW COLUMNS FROM school_profile LIKE 'address'");
  if ($c1 && $c1->num_rows > 0) {
    $st2 = $mysqli->prepare("UPDATE school_profile SET address=?, phone=?, email=? WHERE id=1");
    if ($st2) { $st2->bind_param("sss", $addr, $phone, $email); $st2->execute(); }
  }

  flash_set("success", "Identitas sekolah berhasil disimpan.");
  redirect("/admin/school_profile.php");
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <h5 class="fw-semibold mb-0"><i class="bi bi-building me-1"></i>Identitas Sekolah</h5>
      <div class="text-secondary small">Lengkapi data sekolah untuk header surat dan informasi aplikasi.</div>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="row g-3 mt-2">
    <div class="col-12">
      <label class="form-label">Nama Sekolah</label>
      <input class="form-control" name="school_name" value="<?= e($school["school_name"] ?? "Sekolah") ?>" required>
    </div>

    <div class="col-12">
      <label class="form-label">Alamat</label>
      <textarea class="form-control" name="school_address" rows="2"><?= e($school["school_address"] ?? ($school["address"] ?? "")) ?></textarea>
    </div>

    <div class="col-md-4">
      <label class="form-label">Kota / Alamat Surat</label>
      <input class="form-control" name="city" value="<?= e($school["city"] ?? "") ?>" placeholder="Batang">
      <div class="form-text">Dipakai untuk format: Batang, 03 Januari 2026</div>
    </div>

    <div class="col-md-4">
      <label class="form-label">No. Telp</label>
      <input class="form-control" name="school_phone" value="<?= e($school["school_phone"] ?? ($school["phone"] ?? "")) ?>" placeholder="(0285) .... / 08....">
    </div>

    <div class="col-md-4">
      <label class="form-label">Email</label>
      <input class="form-control" name="school_email" value="<?= e($school["school_email"] ?? ($school["email"] ?? "")) ?>" placeholder="email@sekolah.sch.id">
    </div>

    <div class="col-md-6">
      <label class="form-label">Logo Sekolah (kiri header)</label>
      <input class="form-control" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
      <?php if (!empty($school["logo_path"])): ?>
        <div class="mt-2">
          <img src="<?= e($BASE_URL . "/" . $school["logo_path"]) ?>" style="height:64px;max-width:180px;object-fit:contain;border:1px solid rgba(10,40,80,.15);border-radius:12px;padding:6px;background:#fff">
        </div>
      <?php endif; ?>
    </div>

    <div class="col-md-6">
      <label class="form-label">Pengaturan Lokasi (opsional)</label>
      <div class="row g-2">
        <div class="col-4">
          <input class="form-control" name="geo_lat" value="<?= e($school["geo_lat"] ?? "") ?>" placeholder="Lat">
        </div>
        <div class="col-4">
          <input class="form-control" name="geo_lng" value="<?= e($school["geo_lng"] ?? "") ?>" placeholder="Lng">
        </div>
        <div class="col-4">
          <input class="form-control" name="geo_radius_m" value="<?= e($school["geo_radius_m"] ?? 150) ?>" placeholder="Radius (m)">
        </div>
      </div>
      <div class="form-text">Jika diisi, absensi bisa divalidasi radius dari titik sekolah.</div>
    </div>

    <div class="col-12 d-flex gap-2 mt-2">
      <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
      <a class="btn btn-outline-secondary" href="<?= e($BASE_URL) ?>/admin/dashboard.php"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
