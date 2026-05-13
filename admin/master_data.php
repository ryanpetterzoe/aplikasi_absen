<?php
$title = "Master Data";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");


// Handle edit/delete year
if (isset($_POST["edit_year"])) {
  $id = (int)($_POST["year_id"] ?? 0);
  $name = trim($_POST["year_name"] ?? "");
  if ($id>0 && $name!=="") {
    $st = $mysqli->prepare("UPDATE academic_years SET name=? WHERE id=?");
    $st->bind_param("si",$name,$id);
    $st->execute();
    flash_set("success","Tahun pelajaran diubah.");
  }
  redirect("/admin/master_data.php");
}
if (isset($_POST["delete_year"])) {
  $id = (int)($_POST["year_id"] ?? 0);
  if ($id>0) {
    $st = $mysqli->prepare("DELETE FROM academic_years WHERE id=? AND is_active=0");
    $st->bind_param("i",$id);
    $st->execute();
    flash_set("success","Tahun pelajaran dihapus (jika tidak aktif).");
  }
  redirect("/admin/master_data.php");
}

// Handle edit/delete major
if (isset($_POST["add_major"])) {
  // already handled below
}
if (isset($_POST["edit_major"])) {
  $id = (int)($_POST["major_id"] ?? 0);
  $name = trim($_POST["major_name"] ?? "");
  if ($id>0 && $name!=="") {
    $st = $mysqli->prepare("UPDATE majors SET name=? WHERE id=?");
    $st->bind_param("si",$name,$id);
    $st->execute();
    flash_set("success","Jurusan diubah.");
  }
  redirect("/admin/master_data.php");
}
if (isset($_POST["delete_major"])) {
  $id = (int)($_POST["major_id"] ?? 0);
  if ($id>0) {
    $st = $mysqli->prepare("DELETE FROM majors WHERE id=?");
    $st->bind_param("i",$id);
    $st->execute();
    flash_set("success","Jurusan dihapus.");
  }
  redirect("/admin/master_data.php");
}

// Handle edit/delete class
if (isset($_POST["edit_class"])) {
  $id = (int)($_POST["class_id"] ?? 0);
  $name = trim($_POST["class_name"] ?? "");
  $grade = (int)($_POST["grade"] ?? 0);
  $major_id = ($_POST["major_id"] ?? "")!=="" ? (int)$_POST["major_id"] : null;
  $homeroom = ($_POST["homeroom_teacher_id"] ?? "")!=="" ? (int)$_POST["homeroom_teacher_id"] : null;
  $year_id = ($_POST["academic_year_id"] ?? "")!=="" ? (int)$_POST["academic_year_id"] : null;
  if ($id>0 && $name!=="" && $grade>0) {
    $st = $mysqli->prepare("UPDATE classes SET name=?, grade=?, major_id=?, homeroom_teacher_id=?, academic_year_id=? WHERE id=?");
    $st->bind_param("siiiii",$name,$grade,$major_id,$homeroom,$year_id,$id);
    $st->execute();
    flash_set("success","Kelas diubah.");
  }
  redirect("/admin/master_data.php");
}
if (isset($_POST["toggle_class"])) {
  $id = (int)($_POST["class_id"] ?? 0);
  $val = (int)($_POST["is_active"] ?? 0);
  if ($id>0) {
    $st = $mysqli->prepare("UPDATE classes SET is_active=? WHERE id=?");
    $st->bind_param("ii",$val,$id);
    $st->execute();
    flash_set("success","Status kelas diperbarui.");
  }
  redirect("/admin/master_data.php");
}


// Handle add year
if (isset($_POST["add_year"])) {
  $name = trim($_POST["year_name"] ?? "");
  if ($name!=="") {
    $stmt = $mysqli->prepare("INSERT INTO academic_years (name,is_active) VALUES (?,0)");
    $stmt->bind_param("s",$name);
    $stmt->execute();
    flash_set("success","Tahun pelajaran ditambahkan.");
  }
  redirect("/admin/master_data.php");
}

// set active year
if (isset($_GET["set_active_year"])) {
  $id = (int)$_GET["set_active_year"];
  $mysqli->query("UPDATE academic_years SET is_active=0");
  $stmt = $mysqli->prepare("UPDATE academic_years SET is_active=1 WHERE id=?");
  $stmt->bind_param("i",$id);
  $stmt->execute();
  flash_set("success","Tahun aktif diubah.");
  redirect("/admin/master_data.php");
}

// add major
if (isset($_POST["add_major"])) {
  $name = trim($_POST["major_name"] ?? "");
  if ($name!=="") {
    $stmt = $mysqli->prepare("INSERT INTO majors (name) VALUES (?)");
    $stmt->bind_param("s",$name);
    $stmt->execute();
    flash_set("success","Jurusan ditambahkan.");
  }
  redirect("/admin/master_data.php");
}

// add class
if (isset($_POST["add_class"])) {
  $name = trim($_POST["class_name"] ?? "");
  $grade = (int)($_POST["grade"] ?? 10);
  $major_id = $_POST["major_id"] !== "" ? (int)$_POST["major_id"] : null;
  $homeroom = $_POST["homeroom_teacher_id"] !== "" ? (int)$_POST["homeroom_teacher_id"] : null;
  $year_id = $_POST["academic_year_id"] !== "" ? (int)$_POST["academic_year_id"] : null;

  if ($name!=="") {
    $stmt = $mysqli->prepare("INSERT INTO classes (name,grade,major_id,homeroom_teacher_id,academic_year_id,is_active) VALUES (?,?,?,?,?,1)");
    $stmt->bind_param("siiii",$name,$grade,$major_id,$homeroom,$year_id);
    $stmt->execute();
    flash_set("success","Kelas ditambahkan.");
  }
  redirect("/admin/master_data.php");
}

$years = [];
$ry = $mysqli->query("SELECT * FROM academic_years ORDER BY is_active DESC, id DESC");
while ($ry && $r = $ry->fetch_assoc()) $years[] = $r;

$majors = [];
$rm = $mysqli->query("SELECT * FROM majors ORDER BY name");
while ($rm && $r = $rm->fetch_assoc()) $majors[] = $r;

$teachers = [];
$rt = $mysqli->query("SELECT id,full_name FROM users WHERE role='GURU' AND status='ACTIVE' ORDER BY full_name");
while ($rt && $r = $rt->fetch_assoc()) $teachers[] = $r;

$classes = [];
$rc = $mysqli->query("SELECT c.*, m.name AS major_name, u.full_name AS wali, ay.name AS year_name
  FROM classes c
  LEFT JOIN majors m ON m.id=c.major_id
  LEFT JOIN users u ON u.id=c.homeroom_teacher_id
  LEFT JOIN academic_years ay ON ay.id=c.academic_year_id
  ORDER BY c.grade, c.name");
while ($rc && $r = $rc->fetch_assoc()) $classes[] = $r;

require_once __DIR__ . "/../includes/header.php";
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft p-3">
      <div class="d-flex align-items-center justify-content-between">
        <h5 class="fw-semibold mb-0"><i class="bi bi-database me-1"></i>Master Data</h5>
        <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
      </div>
      <div class="text-secondary small mt-1">Kelola Tahun Pelajaran, Jurusan, dan Kelas (wali kelas memilih guru).</div>

      <div class="mt-2 d-flex gap-2 flex-wrap">
        <div class="btn-group">
          <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import Excel</button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="master_data_import.php?type=years">Tahun Pelajaran</a></li>
            <li><a class="dropdown-item" href="master_data_import.php?type=majors">Jurusan</a></li>
            <li><a class="dropdown-item" href="master_data_import.php?type=classes">Kelas</a></li>
          </ul>
        </div>
        <div class="btn-group">
          <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-download me-1"></i>Template</button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="master_data_template.php?type=years">Tahun Pelajaran</a></li>
            <li><a class="dropdown-item" href="master_data_template.php?type=majors">Jurusan</a></li>
            <li><a class="dropdown-item" href="master_data_template.php?type=classes">Kelas</a></li>
          </ul>
        </div>
        <div class="btn-group">
          <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="master_data_export.php?type=years">Tahun Pelajaran</a></li>
            <li><a class="dropdown-item" href="master_data_export.php?type=majors">Jurusan</a></li>
            <li><a class="dropdown-item" href="master_data_export.php?type=classes">Kelas</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card card-soft p-3">
      <div class="fw-semibold mb-2">Tahun Pelajaran</div>
      <form method="post" class="d-flex gap-2">
        <input class="form-control" name="year_name" placeholder="contoh: 2025/2026" required>
        <button class="btn btn-primary" name="add_year" value="1">Tambah</button>
      </form>

      <div class="mt-3">
        <?php foreach($years as $y): ?>
          <div class="d-flex justify-content-between align-items-center border rounded-3 p-2 mb-2 bg-white">
            <div>
              <div class="fw-semibold"><?= e($y["name"]) ?> <?= $y["is_active"]? "<span class='badge bg-success ms-1'>Aktif</span>":"" ?></div>
              <div class="small text-secondary">ID: <?= (int)$y["id"] ?></div>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="?set_active_year=<?= (int)$y["id"] ?>">Jadikan Aktif</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
            <div class="text-end">
              <form method="post" class="d-inline">
                <input type="hidden" name="year_id" value="<?= (int)$y["id"] ?>">
                <input type="text" name="year_name" class="form-control form-control-sm d-inline-block" style="width:150px" value="<?= e($y["name"]) ?>">
                <button class="btn btn-sm btn-outline-primary ms-1" name="edit_year" value="1"><i class="bi bi-save"></i></button>
              </form>
              <form method="post" class="d-inline">
                <input type="hidden" name="year_id" value="<?= (int)$y["id"] ?>">
                <button class="btn btn-sm btn-outline-danger ms-1" name="delete_year" value="1" onclick="return confirm('Hapus tahun pelajaran ini? Hanya bisa jika tidak aktif.')"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>

  <div class="col-12 col-lg-4">
    <div class="card card-soft p-3">
      <div class="fw-semibold mb-2">Jurusan</div>
      <form method="post" class="d-flex gap-2">
        <input class="form-control" name="major_name" placeholder="contoh: RPL" required>
        <button class="btn btn-primary" name="add_major" value="1">Tambah</button>
      </form>
      <div class="mt-3">
        <?php foreach($majors as $m): ?>
          <div class="border rounded-3 p-2 mb-2 bg-white">
            <div class="fw-semibold"><?= e($m["name"]) ?></div>
            <div class="small text-secondary">ID: <?= (int)$m["id"] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card card-soft p-3">
      <div class="fw-semibold mb-2">Tambah Kelas</div>
      <div class="text-secondary small mb-2">Untuk edit/hapus kelas (ubah wali kelas, nama, jurusan, tahun), gunakan menu <a href="classes.php">Data Kelas</a>.</div>
      <form method="post" class="vstack gap-2">
        <div>
          <label class="form-label small">Nama Kelas</label>
          <input class="form-control" name="class_name" placeholder="contoh: X RPL 1" required>
        </div>
        <div class="row g-2">
          <div class="col-4">
            <label class="form-label small">Tingkat</label>
            <input class="form-control" type="number" name="grade" min="1" max="99" value="1" required>
            <div class="text-secondary small mt-1">Isi bebas sesuai jenjang (contoh SD: 1-6, SMP: 7-9, SMA/SMK: 10-12).</div>
          </div>
          <div class="col-8">
            <label class="form-label small">Jurusan</label>
            <select class="form-select" name="major_id">
              <option value="">--</option>
              <?php foreach($majors as $m): ?>
                <option value="<?= (int)$m["id"] ?>"><?= e($m["name"]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="form-label small">Wali Kelas (Guru)</label>
          <select class="form-select" name="homeroom_teacher_id">
            <option value="">--</option>
            <?php foreach($teachers as $t): ?>
              <option value="<?= (int)$t["id"] ?>"><?= e($t["full_name"]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label small">Tahun Pelajaran</label>
          <select class="form-select" name="academic_year_id">
            <option value="">--</option>
            <?php foreach($years as $y): ?>
              <option value="<?= (int)$y["id"] ?>"><?= e($y["name"]) ?><?= $y["is_active"] ? " (Aktif)" : "" ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" name="add_class" value="1">Simpan Kelas</button>
      </form>
    </div>
  </div>

  <div class="col-12">
    <div class="card card-soft p-3">
      <div class="fw-semibold mb-2">Daftar Kelas</div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Kelas</th>
              <th>Tingkat</th>
              <th>Jurusan</th>
              <th>Wali Kelas</th>
              <th>Tahun</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($classes as $c): ?>
              <tr>
                <td><?= e($c["name"]) ?></td>
                <td><?= (int)$c["grade"] ?></td>
                <td><?= e($c["major_name"]) ?></td>
                <td><?= e($c["wali"]) ?></td>
                <td><?= e($c["year_name"]) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
