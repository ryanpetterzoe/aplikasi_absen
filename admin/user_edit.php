<?php
$title = "Edit User";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$id = (int)($_GET["id"] ?? 0);
if ($id<=0) { flash_set("warning","User tidak ditemukan."); redirect("/admin/master_users.php"); }

$stmt = $mysqli->prepare("SELECT u.*, c.name AS class_name, ay.name AS year_name
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN academic_years ay ON ay.id=u.academic_year_id
  WHERE u.id=? LIMIT 1");
$stmt->bind_param("i",$id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { flash_set("warning","User tidak ditemukan."); redirect("/admin/master_users.php"); }

$role = $user["role"];

$classes = [];
$rc = $mysqli->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY grade,name");
while ($rc && $row = $rc->fetch_assoc()) $classes[] = $row;

$years = [];
$ry = $mysqli->query("SELECT id,name,is_active FROM academic_years ORDER BY is_active DESC, id DESC");
while ($ry && $row = $ry->fetch_assoc()) $years[] = $row;

if ($_SERVER["REQUEST_METHOD"]==="POST") {
  if (isset($_POST["delete_user"])) {
    if ($role==="ADMIN") { flash_set("danger","Tidak bisa menghapus admin."); redirect("/admin/user_edit.php?id=".$id); }

    // hapus foto profil folder
    $dir = __DIR__ . "/../uploads/profile/" . $id . "/";
    if (is_dir($dir)) {
      foreach (glob($dir."*") as $f) @unlink($f);
      @rmdir($dir);
    }
    // delete user (cascade: attendance/leave_requests)
    $del = $mysqli->prepare("DELETE FROM users WHERE id=? AND role<>'ADMIN'");
    $del->bind_param("i",$id);
    $del->execute();

    flash_set("success","User berhasil dihapus.");
    redirect("/admin/master_users.php?role=".$role);
  }

  if (isset($_POST["remove_photo"])) {
    $old = $user["photo_path"] ?? "";
    if ($old) {
      $abs = __DIR__ . "/../" . $old;
      if (is_file($abs)) @unlink($abs);
      // also remove folder if empty
      $dir = dirname($abs);
      @rmdir($dir);
    }
    $up = $mysqli->prepare("UPDATE users SET photo_path=NULL WHERE id=?");
    if ($up) {
      $up->bind_param("i",$id);
      $up->execute();
    } else {
      flash_set("warning","Kolom foto belum tersedia di database. Jalankan DB_UPDATE_add_user_photo.sql");
    }
    flash_set("success","Foto berhasil dihapus.");
    redirect("/admin/user_edit.php?id=".$id);
  }

  // update fields
  $full_name = trim($_POST["full_name"] ?? "");
  $phone_wa = trim($_POST["phone_wa"] ?? "");
  $address = trim($_POST["address"] ?? "");

  $employee_no = trim($_POST["employee_no"] ?? "");
  $nisn = trim($_POST["nisn"] ?? "");

  $class_id = ($_POST["class_id"] ?? "")!=="" ? (int)$_POST["class_id"] : null;
  $academic_year_id = ($_POST["academic_year_id"] ?? "")!=="" ? (int)$_POST["academic_year_id"] : null;

  if ($full_name==="") { flash_set("warning","Nama wajib."); redirect("/admin/user_edit.php?id=".$id); }
  if ($role==="SISWA" && $nisn==="") { flash_set("warning","NISN wajib."); redirect("/admin/user_edit.php?id=".$id); }
  if (in_array($role, ["GURU","KEPSEK","YAYASAN"], true) && $employee_no==="") { flash_set("warning","Nomor pegawai wajib."); redirect("/admin/user_edit.php?id=".$id); }

  $stmt = $mysqli->prepare("UPDATE users SET full_name=?, phone_wa=?, address=?, employee_no=?, nisn=?, class_id=?, academic_year_id=? WHERE id=?");
  $stmt->bind_param("sssssiii", $full_name, $phone_wa, $address, $employee_no, $nisn, $class_id, $academic_year_id, $id);
  // Note: bind types 'i' expects int; passing null requires setting to null variable; we handle by casting above. 
  $stmt->execute();

  // upload photo
  if (!empty($_FILES["photo"]["name"])) {
    $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
      flash_set("danger","Format foto harus jpg/png/webp.");
      redirect("/admin/user_edit.php?id=".$id);
    }
    $dir = __DIR__ . "/../uploads/profile/" . $id . "/";
    ensure_upload_dir($dir);
    $fname = "photo." . $ext;
    if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $dir . $fname)) {
      flash_set("danger","Gagal upload foto.");
      redirect("/admin/user_edit.php?id=".$id);
    }
    $rel = "uploads/profile/" . $id . "/" . $fname;
    $up = $mysqli->prepare("UPDATE users SET photo_path=? WHERE id=?");
    $up->bind_param("si", $rel, $id);
    $up->execute();
  }

  flash_set("success","Perubahan tersimpan.");
  redirect("/admin/user_edit.php?id=".$id);
}

require_once __DIR__ . "/../includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="card card-soft p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-secondary small">Role</div>
          <div class="fw-semibold fs-5"><?= e($role) ?> • <?= e($user["username"]) ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="master_users.php?role=<?= e($role) ?>"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
      </div>
      <hr>

      <div class="d-flex gap-3 align-items-start flex-wrap">
        <div>
          <?php if (!empty($user["photo_path"]) && file_exists(__DIR__ . "/../" . $user["photo_path"])): ?>
            <img src="<?= $BASE_URL . "/" . e($user["photo_path"]) ?>" style="width:120px;height:150px;object-fit:cover;border-radius:16px;border:1px solid rgba(10,40,80,.12);" alt="foto">
          <?php else: ?>
            <div class="border rounded-4 d-flex align-items-center justify-content-center bg-white" style="width:120px;height:150px;">
              <i class="bi bi-person-circle fs-1 text-secondary"></i>
            </div>
          <?php endif; ?>
          <form method="post" class="mt-2">
            <button class="btn btn-sm btn-outline-danger w-100" name="remove_photo" value="1" onclick="return confirm('Hapus foto?')"><i class="bi bi-trash me-1"></i>Hapus Foto</button>
          </form>
        </div>

        <div class="flex-grow-1">
          <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-12">
              <label class="form-label">Nama Lengkap</label>
              <input class="form-control" name="full_name" value="<?= e($user["full_name"]) ?>" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nomor WA</label>
              <input class="form-control" name="phone_wa" value="<?= e($user["phone_wa"] ?? "") ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Foto (opsional)</label>
              <input class="form-control" type="file" name="photo" accept="image/*">
  <?php if (!empty($user["photo_path"])): ?>
    <div class="mt-2">
      <img src="<?= $BASE_URL . '/' . e($user["photo_path"]) ?>" style="height:90px;border-radius:14px;border:1px solid rgba(10,40,80,.15);object-fit:cover;">
    </div>
    <button class="btn btn-outline-danger btn-sm mt-2" name="delete_photo" value="1" type="submit" onclick="return confirm('Hapus foto user ini?')">
      <i class="bi bi-trash me-1"></i>Hapus Foto
    </button>
  <?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label">Alamat</label>
              <textarea class="form-control" name="address" rows="2"><?= e($user["address"] ?? "") ?></textarea>
            </div>

            <?php if ($role==="SISWA"): ?>
              <div class="col-12 col-md-6">
                <label class="form-label">NISN</label>
                <input class="form-control" name="nisn" value="<?= e($user["nisn"] ?? "") ?>" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="class_id" required>
                  <option value="">- pilih -</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?= (int)$c["id"] ?>" <?= ((int)$user["class_id"]===(int)$c["id"])?"selected":"" ?>><?= e($c["name"]) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else: ?>
              <div class="col-12 col-md-6">
                <label class="form-label">Nomor Pegawai</label>
                <input class="form-control" name="employee_no" value="<?= e($user["employee_no"] ?? "") ?>" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Kelas (opsional)</label>
                <select class="form-select" name="class_id">
                  <option value="">-</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?= (int)$c["id"] ?>" <?= ((int)$user["class_id"]===(int)$c["id"])?"selected":"" ?>><?= e($c["name"]) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="col-12 col-md-6">
              <label class="form-label">Tahun Pelajaran</label>
              <select class="form-select" name="academic_year_id" required>
                <option value="">- pilih -</option>
                <?php foreach($years as $y): ?>
                  <option value="<?= (int)$y["id"] ?>" <?= ((int)$user["academic_year_id"]===(int)$y["id"])?"selected":"" ?>><?= e($y["name"]) ?> <?= ((int)$y["is_active"]===1)?"(Aktif)":""
                   ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 d-grid">
              <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
            </div>
          </form>

          <?php if ($role!=="ADMIN"): ?>
          <form method="post" class="mt-3">
            <button class="btn btn-danger w-100" name="delete_user" value="1" onclick="return confirm('Hapus user ini? Semua data absensinya akan ikut terhapus.')">
              <i class="bi bi-trash me-1"></i>Hapus User
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
