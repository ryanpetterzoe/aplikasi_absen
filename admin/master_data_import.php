<?php
$title = "Import Master Data";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$type = $_GET["type"] ?? "majors";
$allowed = ["majors","years","classes"];
if (!in_array($type, $allowed, true)) $type = "majors";

function md_get_str($row, $key) {
  $v = $row[$key] ?? "";
  if (is_string($v)) $v = trim($v);
  return (string)$v;
}

function md_get_int($row, $key, $default = 0) {
  $v = md_get_str($row, $key);
  if ($v === "") return (int)$default;
  if (is_numeric($v)) return (int)$v;
  return (int)$default;
}

$info = [
  "majors" => [
    "label" => "Jurusan",
    "cols" => ["name"],
  ],
  "years" => [
    "label" => "Tahun Pelajaran",
    "cols" => ["name","is_active(1/0)"],
  ],
  "classes" => [
    "label" => "Kelas",
    "cols" => ["grade","name","major_name","homeroom_teacher","academic_year","is_active(1/0)"],
  ],
];

$resultMsg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (empty($_FILES["file"]["name"])) {
    flash_set("warning", "Pilih file Excel/CSV terlebih dahulu.");
    redirect("/admin/master_data_import.php?type=" . urlencode($type));
  }

  $tmp = $_FILES["file"]["tmp_name"];
  if (!is_uploaded_file($tmp)) {
    flash_set("danger", "Upload gagal.");
    redirect("/admin/master_data_import.php?type=" . urlencode($type));
  }

  $ext = strtolower(pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION));
  if (!in_array($ext, ["xlsx","xls","ods","csv"], true)) {
    flash_set("warning", "Format tidak didukung. Gunakan XLSX/CSV.");
    redirect("/admin/master_data_import.php?type=" . urlencode($type));
  }

  $tmpDir = __DIR__ . "/../uploads/tmp/";
  ensure_upload_dir($tmpDir);
  $dest = $tmpDir . "md_" . time() . "_" . rand(1000,9999) . "." . $ext;
  move_uploaded_file($tmp, $dest);

  $rows = excel_read_assoc_rows($dest);
  @unlink($dest);

  if (count($rows) === 0) {
    flash_set("warning", "File kosong atau header tidak ditemukan.");
    redirect("/admin/master_data_import.php?type=" . urlencode($type));
  }

  $inserted = 0;
  $updated = 0;
  $skipped = 0;

  if ($type === "majors") {
    $sel = $mysqli->prepare("SELECT id FROM majors WHERE name=? LIMIT 1");
    $ins = $mysqli->prepare("INSERT INTO majors (name) VALUES (?)");
    foreach ($rows as $r) {
      $name = md_get_str($r, "name");
      if ($name === "") { $skipped++; continue; }
      $sel->bind_param("s", $name);
      $sel->execute();
      if ($sel->get_result()->fetch_assoc()) { $skipped++; continue; }
      $ins->bind_param("s", $name);
      $ins->execute();
      $inserted++;
    }
    $resultMsg = "Import jurusan selesai. Ditambah: {$inserted}. Dilewati: {$skipped}.";
  } elseif ($type === "years") {
    $sel = $mysqli->prepare("SELECT id FROM academic_years WHERE name=? LIMIT 1");
    $ins = $mysqli->prepare("INSERT INTO academic_years (name,is_active) VALUES (?,?)");
    $upd = $mysqli->prepare("UPDATE academic_years SET is_active=? WHERE id=?");

    $toActivate = [];
    foreach ($rows as $r) {
      $name = md_get_str($r, "name");
      if ($name === "") { $skipped++; continue; }
      $is_active = md_get_int($r, "is_active(1/0)", 0) ? 1 : 0;
      $sel->bind_param("s", $name);
      $sel->execute();
      $found = $sel->get_result()->fetch_assoc();
      if ($found) {
        $upd->bind_param("ii", $is_active, $found["id"]);
        $upd->execute();
        $updated++;
        if ($is_active) $toActivate[] = (int)$found["id"];
      } else {
        $ins->bind_param("si", $name, $is_active);
        $ins->execute();
        $newId = (int)$mysqli->insert_id;
        $inserted++;
        if ($is_active) $toActivate[] = $newId;
      }
    }

    // If any row marked active, normalize to those rows
    if (count($toActivate) > 0) {
      $mysqli->query("UPDATE academic_years SET is_active=0");
      foreach ($toActivate as $id) {
        $st = $mysqli->prepare("UPDATE academic_years SET is_active=1 WHERE id=?");
        $st->bind_param("i", $id);
        $st->execute();
      }
    }

    $resultMsg = "Import tahun pelajaran selesai. Ditambah: {$inserted}. Diupdate: {$updated}. Dilewati: {$skipped}.";
  } else { // classes
    $activeYearId = get_active_academic_year_id($mysqli);

    $selMajor = $mysqli->prepare("SELECT id FROM majors WHERE name=? LIMIT 1");
    $insMajor = $mysqli->prepare("INSERT INTO majors (name) VALUES (?)");
    $selYear  = $mysqli->prepare("SELECT id FROM academic_years WHERE name=? LIMIT 1");
    $insYear  = $mysqli->prepare("INSERT INTO academic_years (name,is_active) VALUES (?,0)");

    $findTeacher = $mysqli->prepare("SELECT id FROM users WHERE (employee_no=? OR username=? OR full_name=?) AND role='GURU' LIMIT 1");
    $findClass = $mysqli->prepare("SELECT id FROM classes WHERE grade=? AND name=? LIMIT 1");
    $insClass = $mysqli->prepare("INSERT INTO classes (grade,name,major_id,homeroom_teacher_id,academic_year_id,is_active) VALUES (?,?,?,?,?,?)");
    $updClass = $mysqli->prepare("UPDATE classes SET major_id=?, homeroom_teacher_id=?, academic_year_id=?, is_active=? WHERE id=?");

    foreach ($rows as $r) {
      $grade = md_get_int($r, "grade", 0);
      $name  = md_get_str($r, "name");
      if ($grade <= 0 || $name === "") { $skipped++; continue; }

      $major_name = md_get_str($r, "major_name");
      $major_id = null;
      if ($major_name !== "") {
        $selMajor->bind_param("s", $major_name);
        $selMajor->execute();
        $m = $selMajor->get_result()->fetch_assoc();
        if ($m) $major_id = (int)$m["id"];
        else {
          $insMajor->bind_param("s", $major_name);
          $insMajor->execute();
          $major_id = (int)$mysqli->insert_id;
        }
      }

      $year_name = md_get_str($r, "academic_year");
      $academic_year_id = $activeYearId;
      if ($year_name !== "") {
        $selYear->bind_param("s", $year_name);
        $selYear->execute();
        $y = $selYear->get_result()->fetch_assoc();
        if ($y) $academic_year_id = (int)$y["id"];
        else {
          $insYear->bind_param("s", $year_name);
          $insYear->execute();
          $academic_year_id = (int)$mysqli->insert_id;
        }
      }

      $homeroom_raw = md_get_str($r, "homeroom_teacher");
      $homeroom_id = null;
      if ($homeroom_raw !== "") {
        $findTeacher->bind_param("sss", $homeroom_raw, $homeroom_raw, $homeroom_raw);
        $findTeacher->execute();
        $t = $findTeacher->get_result()->fetch_assoc();
        if ($t) $homeroom_id = (int)$t["id"];
      }

      $is_active = md_get_int($r, "is_active(1/0)", 1) ? 1 : 0;

      $findClass->bind_param("is", $grade, $name);
      $findClass->execute();
      $existing = $findClass->get_result()->fetch_assoc();
      if ($existing) {
        $cid = (int)$existing["id"];
        $mid = $major_id; // can be null
        $hid = $homeroom_id;
        $midParam = $mid === null ? null : $mid;
        $hidParam = $hid === null ? null : $hid;
        $updClass->bind_param("iiiii", $midParam, $hidParam, $academic_year_id, $is_active, $cid);
        $updClass->execute();
        $updated++;
      } else {
        $mid = $major_id;
        $hid = $homeroom_id;
        $insClass->bind_param("isiiii", $grade, $name, $mid, $hid, $academic_year_id, $is_active);
        $insClass->execute();
        $inserted++;
      }
    }

    $resultMsg = "Import kelas selesai. Ditambah: {$inserted}. Diupdate: {$updated}. Dilewati: {$skipped}.";
  }

  flash_set("success", $resultMsg);
  if ($type === "classes") redirect("/admin/classes.php");
  redirect("/admin/master_data.php");
}

require_once __DIR__ . "/../includes/header.php";
?>

<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import Excel - <?= e($info[$type]["label"]) ?></h5>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $type==="classes" ? "classes.php" : "master_data.php" ?>">Kembali</a>
  </div>

  <div class="mt-2 d-flex gap-2 flex-wrap">
    <a class="btn btn-sm <?= $type==="majors"?"btn-primary":"btn-outline-primary" ?>" href="?type=majors">Jurusan</a>
    <a class="btn btn-sm <?= $type==="years"?"btn-primary":"btn-outline-primary" ?>" href="?type=years">Tahun Pelajaran</a>
    <a class="btn btn-sm <?= $type==="classes"?"btn-primary":"btn-outline-primary" ?>" href="?type=classes">Kelas</a>
  </div>

  <div class="mt-3">
    <div class="small text-secondary mb-2">Kolom yang dibutuhkan:</div>
    <ul class="small">
      <?php foreach($info[$type]["cols"] as $c): ?>
        <li><code><?= e($c) ?></code></li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="mt-2 d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-outline-secondary" href="master_data_template.php?type=<?= e($type) ?>"><i class="bi bi-download me-1"></i>Download Template</a>
    <a class="btn btn-sm btn-outline-success" href="master_data_export.php?type=<?= e($type) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Export Data</a>
  </div>

  <form class="mt-3" method="post" enctype="multipart/form-data">
    <div class="mb-2">
      <label class="form-label small">File Excel/CSV</label>
      <input class="form-control" type="file" name="file" accept=".xlsx,.xls,.ods,.csv" required>
    </div>
    <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Import</button>
  </form>

  <div class="alert alert-info mt-3 mb-0">
    Tips: Pastikan nama kolom (header) persis seperti template. Jika tidak ada library Excel di server, sistem otomatis memakai CSV.
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
