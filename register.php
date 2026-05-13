<?php
$title = "Register";
require_once __DIR__ . "/includes/functions.php";

$roles = [
  "KEPSEK" => "Kepala Sekolah",
  "YAYASAN" => "Yayasan",
  "GURU" => "Guru",
  "SISWA" => "Siswa"
];

$classes = [];
$years = [];
$rc = $mysqli->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY grade,name");
while ($rc && $row = $rc->fetch_assoc()) $classes[] = $row;

$ry = $mysqli->query("SELECT id,name,is_active FROM academic_years ORDER BY is_active DESC, id DESC");
while ($ry && $row = $ry->fetch_assoc()) $years[] = $row;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $role = $_POST["role"] ?? "";
  if (!isset($roles[$role])) {
    flash_set("warning", "Role tidak valid.");
    redirect("/register.php");
  }

  $employee_no = trim($_POST["employee_no"] ?? "");
  $nisn = trim($_POST["nisn"] ?? "");
  $full_name = trim($_POST["full_name"] ?? "");
  $class_id = $_POST["class_id"] ?? null;
  $academic_year_id = $_POST["academic_year_id"] ?? null;
  $address = trim($_POST["address"] ?? "");
  $phone_wa = trim($_POST["phone_wa"] ?? "");
  $username = trim($_POST["username"] ?? "");
  $pass = $_POST["password"] ?? "";

  if ($full_name==="" || $username==="" || $pass==="") {
    flash_set("warning", "Nama, username, password wajib diisi.");
    redirect("/register.php");
  }

  // Role requirements
  if ($role === "SISWA" && $nisn==="") {
    flash_set("warning", "NISN wajib untuk siswa.");
    redirect("/register.php");
  }
  if (in_array($role, ["GURU","KEPSEK","YAYASAN"], true) && $employee_no==="") {
    flash_set("warning", "Nomor pegawai wajib.");
    redirect("/register.php");
  }

  // Username unique
  $stmt = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
  $stmt->bind_param("s", $username);
  $stmt->execute();

  $new_id = $mysqli->insert_id;
  if (!empty($_FILES["photo"]["name"]) && $new_id) {
    $dir = __DIR__ . "/uploads/profile/" . $new_id . "/";
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

  if ($stmt->get_result()->fetch_assoc()) {
    flash_set("danger", "Username sudah dipakai.");
    redirect("/register.php");
  }

  $hash = password_hash($pass, PASSWORD_BCRYPT);

  $stmt = $mysqli->prepare("INSERT INTO users
    (role,status,username,password_hash,full_name,phone_wa,address,employee_no,nisn,class_id,academic_year_id,must_change_password)
    VALUES (?, 'PENDING', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
  $stmt->bind_param("ssssssssii",
    $role, $username, $hash, $full_name, $phone_wa, $address, $employee_no, $nisn, $class_id, $academic_year_id
  );
  $stmt->execute();

  flash_set("success", "Pendaftaran berhasil. Menunggu persetujuan admin.");
  redirect("/login.php");
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-8">
    <div class="card card-soft p-3">
      <h5 class="fw-semibold mb-2"><i class="bi bi-person-plus me-1"></i>Register</h5>
      <div class="text-secondary mb-3">Setelah daftar, akun berstatus <b>Pending</b> sampai disetujui admin.</div>

      <form method="post" id="formReg" class="row g-2" enctype="multipart/form-data">
        <div class="col-12">
          <label class="form-label">Registrasi Sebagai</label>
          <select class="form-select" name="role" id="role" required>
            <option value="">-- pilih --</option>
            <?php foreach($roles as $k=>$v): ?>
              <option value="<?= e($k) ?>"><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6" id="wrapNisn" style="display:none;">
          <label class="form-label">NISN</label>
          <input class="form-control" name="nisn" placeholder="NISN">
        </div>

        <div class="col-md-6" id="wrapEmp" style="display:none;">
          <label class="form-label">Nomor Pegawai</label>
          <input class="form-control" name="employee_no" placeholder="Nomor Pegawai">
        </div>

        <div class="col-12">
          <label class="form-label">Nama</label>
          <input class="form-control" name="full_name" required>
        </div>

        <div class="col-md-6">
          <label class="form-label">Kelas</label>
          <select class="form-select" name="class_id">
            <option value="">-- pilih kelas --</option>
            <?php foreach($classes as $c): ?>
              <option value="<?= (int)$c["id"] ?>"><?= e($c["name"]) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="text-secondary small mt-1">Untuk guru/kepsek/yayasan boleh dikosongkan.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Tahun Pelajaran</label>
          <select class="form-select" name="academic_year_id">
            <option value="">-- pilih tahun --</option>
            <?php foreach($years as $y): ?>
              <option value="<?= (int)$y["id"] ?>"><?= e($y["name"]) ?><?= $y["is_active"] ? " (Aktif)" : "" ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Alamat</label>
          <textarea class="form-control" name="address" rows="2"></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Nomor WA</label>
          <input class="form-control" name="phone_wa" placeholder="08xxxxxxxxxx">
        </div>

        <div class="col-md-6">
          <label class="form-label">Username</label>
          <input class="form-control" name="username" required>
        </div>

        <div class="col-12">
          <label class="form-label">Password</label>
          <input class="form-control" type="password" name="password" required>
        </div>

        
        <div class="col-12">
          <label class="form-label">Foto (opsional)</label>
          <input class="form-control" type="file" name="photo" accept="image/*" capture="user">
          <div class="text-secondary small mt-1">Bisa pakai kamera HP (opsional).</div>
        </div>
<div class="col-12 d-grid mt-2">
          <button class="btn btn-primary btn-lg" type="submit">Daftar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const role = document.getElementById("role");
const wrapNisn = document.getElementById("wrapNisn");
const wrapEmp = document.getElementById("wrapEmp");

role.addEventListener("change", () => {
  const v = role.value;
  wrapNisn.style.display = (v === "SISWA") ? "block" : "none";
  wrapEmp.style.display = (v === "GURU" || v === "KEPSEK" || v === "YAYASAN") ? "block" : "none";
});
</script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
