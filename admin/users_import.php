<?php
$title = "Import Users (Excel)";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$role = strtoupper(trim($_GET["role"] ?? ($_POST["role"] ?? "SISWA")));
$allowed = ["SISWA","GURU","KEPSEK","YAYASAN","ADMIN"];
if (!in_array($role, $allowed, true)) $role = "SISWA";

$classes = [];
$rc = $mysqli->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY grade,name");
while ($rc && $r = $rc->fetch_assoc()) $classes[] = $r;

$years = [];
$ry = $mysqli->query("SELECT id,name,is_active FROM academic_years ORDER BY is_active DESC, id DESC");
while ($ry && $r = $ry->fetch_assoc()) $years[] = $r;

function find_class_id_by_name($classes, $name) {
  $name = trim((string)$name);
  if ($name === "") return null;
  foreach ($classes as $c) {
    if (mb_strtolower(trim($c["name"])) === mb_strtolower($name)) return (int)$c["id"];
  }
  return null;
}

function find_year_id_by_label($years, $label) {
  $label = trim((string)$label);
  if ($label === "") return null;
  foreach ($years as $y) {
    if (mb_strtolower(trim($y["name"])) === mb_strtolower($label)) return (int)$y["id"];
  }
  return null;
}

$result_summary = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (empty($_FILES["file"]["name"])) {
    flash_set("warning", "Pilih file Excel/CSV terlebih dulu.");
    redirect("/admin/users_import.php?role=" . $role);
  }

  $tmp = $_FILES["file"]["tmp_name"];
  $orig = $_FILES["file"]["name"];
  $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

  if (!in_array($ext, ["xlsx","xls","ods","csv"], true)) {
    flash_set("danger", "Format file tidak didukung. Gunakan .xlsx / .csv");
    redirect("/admin/users_import.php?role=" . $role);
  }

  if (in_array($ext, ["xlsx","xls","ods"], true) && !excel_available()) {
    flash_set("warning", "Library XLSX belum terpasang. Silakan import pakai CSV atau install PhpSpreadsheet via Composer.");
    redirect("/admin/users_import.php?role=" . $role);
  }

  // Simpan sementara agar bisa dibaca ulang
  $uploadDir = __DIR__ . "/../uploads/tmp/";
  ensure_upload_dir($uploadDir);
  $safeName = "import_" . date("Ymd_His") . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
  $dest = $uploadDir . $safeName;
  if (!move_uploaded_file($tmp, $dest)) {
    flash_set("danger", "Gagal upload file.");
    redirect("/admin/users_import.php?role=" . $role);
  }

  $rows = excel_read_assoc_rows($dest);
  @unlink($dest);

  // Normalisasi header: lower + trim
  $norm = [];
  foreach ($rows as $r) {
    $rr = [];
    foreach ($r as $k => $v) {
      $kk = mb_strtolower(trim((string)$k));
      $rr[$kk] = $v;
    }
    $norm[] = $rr;
  }

  $inserted = 0;
  $skipped = 0;
  $errors = [];

  $activeYearId = get_active_academic_year_id($mysqli);

  foreach ($norm as $idx => $r) {
    $line = $idx + 2; // header row assumed at row 1

    $full_name = trim((string)($r["full_name"] ?? ""));
    $username  = trim((string)($r["username"] ?? ""));
    $password  = (string)($r["password"] ?? "");
    $employee_no = trim((string)($r["employee_no"] ?? ""));
    $nisn      = trim((string)($r["nisn"] ?? ""));
    $class_name = trim((string)($r["class_name"] ?? ""));
    $academic_year_label = trim((string)($r["academic_year"] ?? ""));
    $phone_wa  = trim((string)($r["phone_wa"] ?? ""));
    $address   = trim((string)($r["address"] ?? ""));

    if ($full_name === "" || $username === "") {
      $errors[] = "Baris {$line}: full_name dan username wajib.";
      $skipped++;
      continue;
    }

    if ($role === "SISWA") {
      if ($nisn === "") {
        $errors[] = "Baris {$line}: nisn wajib untuk SISWA.";
        $skipped++;
        continue;
      }
      if ($class_name === "") {
        $errors[] = "Baris {$line}: class_name wajib untuk SISWA.";
        $skipped++;
        continue;
      }
    } elseif (in_array($role, ["GURU","KEPSEK","YAYASAN"], true)) {
      if ($employee_no === "") {
        $errors[] = "Baris {$line}: employee_no wajib untuk {$role}.";
        $skipped++;
        continue;
      }
    }

    // Cek username unik
    $st = $mysqli->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    $st->bind_param("s", $username);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) {
      $errors[] = "Baris {$line}: username '{$username}' sudah ada (di-skip).";
      $skipped++;
      continue;
    }

    $class_id = null;
    if ($class_name !== "") {
      $class_id = find_class_id_by_name($classes, $class_name);
      if ($class_id === null && $role === "SISWA") {
        $errors[] = "Baris {$line}: class_name '{$class_name}' tidak ditemukan di master kelas.";
        $skipped++;
        continue;
      }
    }

    $academic_year_id = find_year_id_by_label($years, $academic_year_label);
    if ($academic_year_id === null) $academic_year_id = $activeYearId;

    $must_change_password = 0;
    if (trim($password) === "") {
      $password = "123456";
      $must_change_password = 1;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $mysqli->prepare(
      "INSERT INTO users (role,status,username,password_hash,must_change_password,full_name,phone_wa,address,employee_no,nisn,class_id,academic_year_id)
       VALUES (?, 'ACTIVE', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
      "sssisssssii",
      $role,
      $username,
      $hash,
      $must_change_password,
      $full_name,
      $phone_wa,
      $address,
      $employee_no,
      $nisn,
      $class_id,
      $academic_year_id
    );

    if (!$stmt->execute()) {
      $errors[] = "Baris {$line}: gagal insert ({$stmt->error})";
      $skipped++;
      continue;
    }
    $inserted++;
  }

  $result_summary = [
    "inserted" => $inserted,
    "skipped" => $skipped,
    "errors" => $errors,
  ];
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import Users (Excel)</h5>
    <a class="btn btn-sm btn-outline-secondary" href="master_users.php?role=<?= e($role) ?>">Kembali</a>
  </div>

  <div class="alert alert-info mt-3 small mb-2">
    <div class="fw-semibold">Format kolom yang didukung:</div>
    <code>full_name, username, password, employee_no, nisn, class_name, academic_year, phone_wa, address</code>
    <div class="mt-2">
      • Jika <code>password</code> kosong, akan di-set <b>123456</b> dan user diwajibkan ganti password.
      <br>• Untuk <b>SISWA</b>: <code>nisn</code> dan <code>class_name</code> wajib.
      <br>• Untuk <b>GURU/KEPSEK/YAYASAN</b>: <code>employee_no</code> wajib.
    </div>
  </div>

  <?php if (!excel_available()): ?>
    <div class="alert alert-warning small">
      Library XLSX belum terpasang. Kamu masih bisa import via <b>CSV</b>.
      <br>Untuk support .xlsx: install Composer lalu jalankan <code>composer require phpoffice/phpspreadsheet</code> di folder project.
    </div>
  <?php endif; ?>

  <?php if ($result_summary): ?>
    <div class="alert alert-success mt-3">
      Berhasil import: <b><?= (int)$result_summary["inserted"] ?></b> user.
      Di-skip: <b><?= (int)$result_summary["skipped"] ?></b>.
    </div>
    <?php if (!empty($result_summary["errors"])): ?>
      <div class="alert alert-warning small">
        <div class="fw-semibold mb-1">Catatan / Error:</div>
        <ul class="mb-0">
          <?php foreach($result_summary["errors"] as $er): ?>
            <li><?= e($er) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="post" class="mt-3" enctype="multipart/form-data">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label small">Role yang di-import</label>
        <select class="form-select" name="role">
          <?php foreach(["SISWA","GURU","KEPSEK","YAYASAN"] as $r): ?>
            <option value="<?= e($r) ?>" <?= $role===$r?"selected":"" ?>><?= e($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-8">
        <label class="form-label small">File (.xlsx / .csv)</label>
        <input class="form-control" type="file" name="file" accept=".xlsx,.xls,.ods,.csv" required>
      </div>
      <div class="col-12 d-flex gap-2 flex-wrap mt-1">
        <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Import</button>
        <a class="btn btn-outline-success" href="users_template.php?role=<?= e($role) ?>"><i class="bi bi-download me-1"></i>Download Template</a>
      </div>
    </div>
  </form>

  <div class="mt-4">
    <div class="fw-semibold mb-2">Referensi Master Kelas (class_name)</div>
    <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Kelas</th></tr></thead>
        <tbody>
          <?php foreach($classes as $c): ?>
            <tr><td><?= e($c["name"]) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
