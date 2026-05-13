<?php
$title = "Cetak Surat Rekap Siswa";
require_once __DIR__ . "/includes/functions.php";
require_login();
require_role("GURU");

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");
$student_id = (int)($_GET["student_id"] ?? 0);

$students = [];
$rc = $mysqli->query("SELECT u.id,u.full_name,u.nisn,c.name AS class_name FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  WHERE u.role='SISWA' AND u.status='ACTIVE' AND u.is_alumni=0
  ORDER BY c.name,u.full_name");
while ($rc && $row = $rc->fetch_assoc()) $students[] = $row;

$school = $mysqli->query("SELECT * FROM school_profile WHERE id=1")->fetch_assoc();

$report = null;
$detail = [];
$leave_count = 0;
if ($student_id > 0) {
  $stmt = $mysqli->prepare("SELECT u.full_name,u.nisn,c.name AS class_name FROM users u LEFT JOIN classes c ON c.id=u.class_id WHERE u.id=? AND u.role='SISWA' LIMIT 1");
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $report = $stmt->get_result()->fetch_assoc();

  if ($report) {
    $stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC");
    // hitung ijin
    $st2 = $mysqli->prepare("SELECT COUNT(*) c FROM leave_requests WHERE user_id=? AND leave_date BETWEEN ? AND ?");
    $st2->bind_param("iss", $student_id, $from, $to);
    $st2->execute();
    $leave_count = (int)($st2->get_result()->fetch_assoc()["c"] ?? 0);

    $stmt->bind_param("iss", $student_id, $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $detail[] = $row;
  }
}

function count_status($detail, $key, $value){
  $n=0;
  foreach($detail as $d) if (($d[$key] ?? null) === $value) $n++;
  return $n;
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-printer me-1"></i>Cetak Surat Rekap Absensi Siswa</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>

  <form class="row g-2 mt-2">
    <div class="col-12 col-md-6">
      <label class="form-label small">Pilih Siswa</label>
      <select class="form-select" name="student_id" required>
        <option value="">-- pilih --</option>
        <?php foreach($students as $s): ?>
          <option value="<?= (int)$s["id"] ?>" <?= ($student_id===(int)$s["id"])?"selected":"" ?>>
            <?= e($s["full_name"]) ?> • <?= e($s["class_name"]) ?> • NISN <?= e($s["nisn"]) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small">Dari</label>
      <input type="date" class="form-control" name="from" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small">Sampai</label>
      <input type="date" class="form-control" name="to" value="<?= e($to) ?>">
    </div>
    <div class="col-12 col-md-2 d-grid align-items-end">
      <button class="btn btn-primary mt-4" type="submit">Tampilkan</button>
    </div>
  </form>

  <?php if ($report): ?>
    <hr>
    <a class="btn btn-outline-primary" target="_blank" href="teacher_letter_print.php?student_id=<?= (int)$student_id ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">
      <i class="bi bi-printer me-1"></i>Print Surat (Format Formal)
    </a>

    <div class="mt-3">
      <div class="fw-semibold">Ringkasan</div>
      <div class="text-secondary small">
        Hadir: <?= count($detail) ?> • Telat: <?= count_status($detail,"status_in","LATE") ?> • Ijin: <?= (int)$leave_count ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
