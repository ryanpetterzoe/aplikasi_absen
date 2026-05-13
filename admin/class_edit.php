<?php
$title = "Edit Kelas";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$id = (int)($_GET["id"] ?? 0);
if ($id<=0) { flash_set("warning","Kelas tidak ditemukan."); redirect("/admin/classes.php"); }

// ambil data kelas
$st = $mysqli->prepare("SELECT * FROM classes WHERE id=? LIMIT 1");
if (!$st) die("Query error: ".$mysqli->error);
$st->bind_param("i", $id);
$st->execute();
$cls = $st->get_result()->fetch_assoc();
if (!$cls) { flash_set("warning","Kelas tidak ditemukan."); redirect("/admin/classes.php"); }

// dropdown data
$majors = [];
$r = $mysqli->query("SELECT id, name FROM majors ORDER BY name ASC");
if ($r) $majors = $r->fetch_all(MYSQLI_ASSOC);

$years = [];
$r = $mysqli->query("SELECT id, name, is_active FROM academic_years ORDER BY is_active DESC, name DESC");
if ($r) $years = $r->fetch_all(MYSQLI_ASSOC);

$teachers = [];
$r = $mysqli->query("SELECT id, full_name FROM users WHERE role='GURU' AND status='ACTIVE' ORDER BY full_name ASC");
if ($r) $teachers = $r->fetch_all(MYSQLI_ASSOC);

// handle save
if (isset($_POST["save"])) {
  $name = trim($_POST["name"] ?? "");
  $grade = (int)($_POST["grade"] ?? 0);
  $major_id = ($_POST["major_id"] ?? "") !== "" ? (int)$_POST["major_id"] : null;
  $homeroom = ($_POST["homeroom_teacher_id"] ?? "") !== "" ? (int)$_POST["homeroom_teacher_id"] : null;
  $year_id = ($_POST["academic_year_id"] ?? "") !== "" ? (int)$_POST["academic_year_id"] : null;

  if ($name === "" || $grade <= 0) {
    flash_set("warning","Nama dan tingkat wajib diisi.");
    redirect("/admin/class_edit.php?id=".$id);
  }

  $up = $mysqli->prepare("UPDATE classes SET name=?, grade=?, major_id=?, homeroom_teacher_id=?, academic_year_id=? WHERE id=?");
  if (!$up) die("Query error: ".$mysqli->error);
  $up->bind_param("siiiii", $name, $grade, $major_id, $homeroom, $year_id, $id);
  $up->execute();

  flash_set("success","Kelas berhasil diperbarui.");
  redirect("/admin/class_edit.php?id=".$id);
}

// handle toggle active
if (isset($_POST["toggle"])) {
  $newVal = (int)(1 - (int)($cls["is_active"] ?? 1));
  $up = $mysqli->prepare("UPDATE classes SET is_active=? WHERE id=?");
  if (!$up) die("Query error: ".$mysqli->error);
  $up->bind_param("ii", $newVal, $id);
  $up->execute();
  flash_set("success","Status kelas berhasil diubah.");
  redirect("/admin/class_edit.php?id=".$id);
}

require_once __DIR__ . "/../includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-7">
    <div class="card card-soft p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div class="fw-semibold fs-5"><i class="bi bi-easel2 me-1"></i>Edit Kelas</div>
        <a class="btn btn-outline-secondary" href="classes.php"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
      </div>
      <hr>

      <form method="post" class="row g-2">
        <div class="col-12">
          <label class="form-label">Nama Kelas</label>
          <input class="form-control" name="name" value="<?= e($cls["name"]) ?>" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Tingkat</label>
          <input class="form-control" type="number" name="grade" min="1" max="99" value="<?= (int)$cls["grade"] ?>" required>
          <div class="text-secondary small mt-1">Bisa untuk SD (1-6), SMP (7-9), SMA/SMK (10-12), dll.</div>
        </div>

        <div class="col-12 col-md-8">
          <label class="form-label">Jurusan (opsional)</label>
          <select class="form-select" name="major_id">
            <option value="">-</option>
            <?php foreach($majors as $m): ?>
              <option value="<?= (int)$m["id"] ?>" <?= ((int)$cls["major_id"]===(int)$m["id"])?"selected":"" ?>><?= e($m["name"]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Wali Kelas (opsional)</label>
          <select class="form-select" name="homeroom_teacher_id">
            <option value="">-</option>
            <?php foreach($teachers as $t): ?>
              <option value="<?= (int)$t["id"] ?>" <?= ((int)$cls["homeroom_teacher_id"]===(int)$t["id"])?"selected":"" ?>><?= e($t["full_name"]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Tahun Pelajaran (opsional)</label>
          <select class="form-select" name="academic_year_id">
            <option value="">-</option>
            <?php foreach($years as $y): ?>
              <option value="<?= (int)$y["id"] ?>" <?= ((int)$cls["academic_year_id"]===(int)$y["id"])?"selected":"" ?>>
                <?= e($y["name"]) ?> <?= ((int)$y["is_active"]===1)?"(Aktif)":""
                ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-primary" name="save" value="1" type="submit"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
        </div>
      </form>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-6 d-grid">
          <form method="post" onsubmit="return confirm('Ubah status kelas?')">
            <button class="btn btn-outline-secondary w-100" name="toggle" value="1" type="submit">
              <?= ((int)($cls["is_active"] ?? 1)===1) ? 'Nonaktifkan Kelas' : 'Aktifkan Kelas' ?>
            </button>
          </form>
        </div>
        <div class="col-12 col-md-6 d-grid">
          <form method="post" action="class_delete.php" onsubmit="return confirm('Hapus kelas ini? Siswa yang terkait akan dikosongkan kelasnya.')">
            <input type="hidden" name="id" value="<?= (int)$cls["id"] ?>">
            <button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-trash me-1"></i>Hapus Kelas</button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
