<?php
$title = "Data Users";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$role = $_GET["role"] ?? "SISWA";
$allowed = ["SISWA","GURU","KEPSEK","YAYASAN","ADMIN"];
if (!in_array($role, $allowed, true)) $role = "SISWA";

$classes = [];
$rc = $mysqli->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY grade,name");
while ($rc && $row = $rc->fetch_assoc()) $classes[] = $row;

$years = [];
$ry = $mysqli->query("SELECT id,name,is_active FROM academic_years ORDER BY is_active DESC, id DESC");
while ($ry && $row = $ry->fetch_assoc()) $years[] = $row;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $full_name = trim($_POST["full_name"] ?? "");
  $username = trim($_POST["username"] ?? "");
  $pass = $_POST["password"] ?? "";
  $employee_no = trim($_POST["employee_no"] ?? "");
  $nisn = trim($_POST["nisn"] ?? "");
  $class_id = $_POST["class_id"] ?? null;
  $academic_year_id = $_POST["academic_year_id"] ?? null;

  if ($full_name==="" || $username==="" || $pass==="") {
    flash_set("warning","Nama, username, password wajib.");
    redirect("/admin/master_users.php?role=".$role);
  }

  $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  if ($stmt->get_result()->fetch_assoc()) {
    flash_set("danger","Username sudah dipakai.");
    redirect("/admin/master_users.php?role=".$role);
  }

  if ($role==="SISWA" && $nisn==="") {
    flash_set("warning","NISN wajib untuk siswa.");
    redirect("/admin/master_users.php?role=".$role);
  }
  if (in_array($role, ["GURU","KEPSEK","YAYASAN"], true) && $employee_no==="") {
    flash_set("warning","Nomor pegawai wajib.");
    redirect("/admin/master_users.php?role=".$role);
  }

  $hash = password_hash($pass, PASSWORD_BCRYPT);
  $stmt = $mysqli->prepare("INSERT INTO users (role,status,username,password_hash,full_name,employee_no,nisn,class_id,academic_year_id)
    VALUES (?, 'ACTIVE', ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("ssssssii", $role, $username, $hash, $full_name, $employee_no, $nisn, $class_id, $academic_year_id);
  $stmt->execute();

  $new_id = $mysqli->insert_id;
  if (!empty($_FILES["photo"]["name"]) && $new_id) {
    $dir = __DIR__ . "/../uploads/profile/" . $new_id . "/";
    ensure_upload_dir($dir);
    $ext = strtolower(pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION));
    if (in_array($ext, ["jpg","jpeg","png","webp"], true)) {
      $fname = "photo." . $ext;
      if (move_uploaded_file($_FILES["photo"]["tmp_name"], $dir . $fname)) {
        $rel = "uploads/profile/" . $new_id . "/" . $fname;
        $up = $mysqli->prepare("UPDATE users SET photo_path=? WHERE id=?");
        $up->bind_param("si", $rel, $new_id);
        $up->execute();
      }
    }
  }

  flash_set("success","User berhasil ditambahkan.");
  redirect("/admin/master_users.php?role=".$role);
}

if (isset($_GET["reset"])) {
  $id = (int)$_GET["reset"];
  $default = "123456";
  $hash = password_hash($default, PASSWORD_BCRYPT);
  $stmt = $mysqli->prepare("UPDATE users SET password_hash=?, must_change_password=1 WHERE id=? AND role<> 'ADMIN'");
  $stmt->bind_param("si", $hash, $id);
  $stmt->execute();
  flash_set("success","Password direset ke 123456 (user wajib ganti).");
  redirect("/admin/master_users.php?role=".$role);
}

$res = $mysqli->prepare("SELECT u.*, c.name AS class_name FROM users u LEFT JOIN classes c ON c.id=u.class_id WHERE u.role=? ORDER BY u.created_at DESC");
$res->bind_param("s", $role);
$res->execute();
$result = $res->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) $rows[] = $r;

require_once __DIR__ . "/../includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-people me-1"></i>Data <?= e($role) ?></h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>

  <div class="mt-2 d-flex gap-2 flex-wrap">
    <?php foreach(["SISWA","GURU","KEPSEK","YAYASAN"] as $r): ?>
      <a class="btn btn-sm <?= ($role===$r)?"btn-primary":"btn-outline-primary" ?>" href="?role=<?= e($r) ?>"><?= e($r) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="mt-2 d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-success" href="users_import.php?role=<?= e($role) ?>"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import Excel</a>
    <a class="btn btn-sm btn-outline-secondary" href="users_template.php?role=<?= e($role) ?>"><i class="bi bi-download me-1"></i>Template</a>
    <a class="btn btn-sm btn-outline-success" href="users_export.php?role=<?= e($role) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
  </div>

  <div class="accordion mt-3" id="accAdd">
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdd">
          <i class="bi bi-plus-circle me-2"></i>Tambah <?= e($role) ?>
        </button>
      </h2>
      <div id="collapseAdd" class="accordion-collapse collapse" data-bs-parent="#accAdd">
        <div class="accordion-body">
          <form method="post" class="row g-2" enctype="multipart/form-data">
            <div class="col-12">
              <label class="form-label small">Nama</label>
              <input class="form-control" name="full_name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Username</label>
              <input class="form-control" name="username" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small">Password</label>
              <input class="form-control" type="password" name="password" required>
            </div>

            <?php if ($role==="SISWA"): ?>
              <div class="col-md-6">
                <label class="form-label small">NISN</label>
                <input class="form-control" name="nisn" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Kelas</label>
                <select class="form-select" name="class_id" required>
                  <option value="">-- pilih --</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?= (int)$c["id"] ?>"><?= e($c["name"]) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else: ?>
              <div class="col-md-6">
                <label class="form-label small">Nomor Pegawai</label>
                <input class="form-control" name="employee_no" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small">Kelas (opsional)</label>
                <select class="form-select" name="class_id">
                  <option value="">-- kosongkan --</option>
                  <?php foreach($classes as $c): ?>
                    <option value="<?= (int)$c["id"] ?>"><?= e($c["name"]) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="col-md-6">
              <label class="form-label small">Tahun Pelajaran</label>
              <select class="form-select" name="academic_year_id">
                <option value="">-- pilih --</option>
                <?php foreach($years as $y): ?>
                  <option value="<?= (int)$y["id"] ?>"><?= e($y["name"]) ?><?= $y["is_active"] ? " (Aktif)" : "" ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label small">Foto (opsional)</label>
              <input class="form-control" type="file" name="photo" accept="image/*" capture="user">
            </div>
            <div class="col-12 d-grid">
              <button class="btn btn-primary" type="submit">Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Username</th>
          <th>Status</th>
          <th>Info</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($r["photo_path"]) && file_exists(__DIR__ . "/../" . $r["photo_path"])): ?>
                  <img src="<?= $BASE_URL . "/" . e($r["photo_path"]) ?>" style="width:36px;height:36px;border-radius:12px;object-fit:cover;border:1px solid rgba(10,40,80,.12);" alt="foto">
                <?php else: ?>
                  <div class="bg-white border rounded-3 d-flex align-items-center justify-content-center" style="width:36px;height:36px;"><i class="bi bi-person text-secondary"></i></div>
                <?php endif; ?>
                <div><?= e($r["full_name"]) ?></div>
              </div>
            </td>
            <td><?= e($r["username"]) ?></td>
            <td><span class="badge bg-<?= $r["status"]==="ACTIVE"?"success":($r["status"]==="PENDING"?"warning text-dark":"secondary") ?>"><?= e($r["status"]) ?></span></td>
            <td class="small">
              <?= $role==="SISWA" ? "NISN: ".e($r["nisn"])."<br>Kelas: ".e($r["class_name"]) : "NIP: ".e($r["employee_no"]) ?>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary me-1" href="user_edit.php?id=<?= (int)$r["id"] ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
              <?php if ($role!=="ADMIN"): ?>

                <form method="post" action="user_edit.php?id=<?= (int)$r["id"] ?>" class="d-inline">
                  <input type="hidden" name="delete_user" value="1">
                  <button class="btn btn-sm btn-outline-danger me-1" type="submit" onclick="return confirm('Hapus user ini? Semua data absensinya akan ikut terhapus.')"><i class="bi bi-trash me-1"></i>Hapus</button>
                </form>

                <a class="btn btn-sm btn-outline-primary" href="?role=<?= e($role) ?>&reset=<?= (int)$r["id"] ?>" onclick="return confirm('Reset password ke 123456?')">
                  <i class="bi bi-key me-1"></i>Reset
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
