<?php
$title = "Profil";
require_once __DIR__ . "/includes/functions.php";
require_login();

$u = current_user();
$user_id = (int)$u["id"];

// Ambil data user terbaru (termasuk foto)
$stmt = $mysqli->prepare("SELECT id, username, full_name, role, nisn, employee_no, class_id, academic_year_id, phone_wa, address, photo_path
                          FROM users WHERE id=? LIMIT 1");
if (!$stmt) {
  die("Query error: " . $mysqli->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) redirect("/logout.php");

$photo_path = $user["photo_path"] ?? null;

// Handle upload/hapus foto
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Hapus foto
  if (isset($_POST["delete_photo"])) {
    if (!empty($photo_path)) {
      $abs = __DIR__ . "/" . $photo_path;
      if (file_exists($abs)) @unlink($abs);
    }
    $stmt2 = $mysqli->prepare("UPDATE users SET photo_path=NULL WHERE id=?");
    if ($stmt2) {
      $stmt2->bind_param("i", $user_id);
      $stmt2->execute();
    }
    flash_set("success","Foto berhasil dihapus.");
    redirect("/profile.php");
  }

  // Upload foto baru
  if (isset($_FILES["photo"]) && !empty($_FILES["photo"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
      flash_set("danger","Format foto harus jpg/png/webp.");
      redirect("/profile.php");
    }

    $dir = __DIR__ . "/uploads/profile/" . $user_id . "/";
    ensure_upload_dir($dir);

    // Simpan sebagai photo.jpg agar konsisten (konversi: kita simpan asli ekstensi; paling aman)
    $fname = "photo." . $ext;
    $dest = $dir . $fname;

    if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $dest)) {
      flash_set("danger","Gagal upload foto. Pastikan folder uploads bisa ditulis.");
      redirect("/profile.php");
    }

    // Hapus foto lama jika beda file
    if (!empty($photo_path)) {
      $old_abs = __DIR__ . "/" . $photo_path;
      if ($old_abs !== $dest && file_exists($old_abs)) @unlink($old_abs);
    }

    $rel = "uploads/profile/" . $user_id . "/" . $fname;
    $stmt3 = $mysqli->prepare("UPDATE users SET photo_path=? WHERE id=?");
    if (!$stmt3) {
      flash_set("danger","Gagal simpan foto ke database: " . $mysqli->error);
      redirect("/profile.php");
    }
    $stmt3->bind_param("si", $rel, $user_id);
    $stmt3->execute();

    flash_set("success","Foto berhasil diperbarui.");
    redirect("/profile.php");
  }
}

require_once __DIR__ . "/includes/header.php";

// Path untuk render
$photo_url = null;
if (!empty($photo_path)) {
  $abs = __DIR__ . "/" . $photo_path;
  if (file_exists($abs)) $photo_url = $BASE_URL . "/" . $photo_path;
}
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-6">
    <div class="card card-soft p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-semibold fs-5"><i class="bi bi-person-circle me-1"></i>Profil Saya</div>
      </div>
      <hr>

      <div class="text-center">
        <?php if ($photo_url): ?>
          <img src="<?= e($photo_url) ?>" alt="foto" style="width:120px;height:120px;object-fit:cover;border-radius:22px;border:1px solid rgba(10,40,80,.15);box-shadow:0 12px 26px rgba(10,40,80,.12);">
        <?php else: ?>
          <div style="width:120px;height:120px;border-radius:22px;border:1px dashed rgba(10,40,80,.25);display:flex;align-items:center;justify-content:center;margin:0 auto;">
            <i class="bi bi-person-bounding-box fs-1 text-primary"></i>
          </div>
        <?php endif; ?>

        <div class="mt-3 fw-semibold"><?= e($user["full_name"]) ?></div>
        <div class="text-secondary small"><?= e(role_label($user["role"])) ?> • <?= e($user["username"]) ?></div>
      </div>

      <div class="mt-3">
        <div class="small text-secondary">Nomor WA</div>
        <div class="fw-semibold"><?= e($user["phone_wa"] ?? "-") ?></div>

        <div class="small text-secondary mt-2">Alamat</div>
        <div class="fw-semibold"><?= e($user["address"] ?? "-") ?></div>
      </div>

      <hr>

      <form method="post" enctype="multipart/form-data" class="row g-2">
        <div class="col-12">
          <label class="form-label">Ubah Foto Profil</label>
          <input class="form-control" type="file" name="photo" accept="image/*" capture="user">
          <div class="text-secondary small mt-1">Bisa ambil dari kamera HP.</div>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Simpan Foto</button>
        </div>
      </form>

      <?php if ($photo_url): ?>
        <form method="post" class="mt-2 d-grid">
          <button class="btn btn-outline-danger" name="delete_photo" value="1" type="submit" onclick="return confirm('Hapus foto profil?')">
            <i class="bi bi-trash me-1"></i>Hapus Foto
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
