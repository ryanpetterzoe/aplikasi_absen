<?php
$title = "Rekap Absensi";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$type = $_GET["type"] ?? "daily"; // daily/weekly/semester/yearly
$date = $_GET["date"] ?? date("Y-m-d");
$year = $_GET["year"] ?? date("Y");
$class_id = $_GET["class_id"] ?? "";
$major_id = $_GET["major_id"] ?? "";

$classes = [];
$rc = $mysqli->query("SELECT c.id,c.name FROM classes c WHERE c.is_active=1 ORDER BY c.grade,c.name");
while ($rc && $r = $rc->fetch_assoc()) $classes[] = $r;

$majors = [];
$rm = $mysqli->query("SELECT id,name FROM majors ORDER BY name");
while ($rm && $r = $rm->fetch_assoc()) $majors[] = $r;

$range_from = $range_to = null;
if ($type === "daily") {
  $range_from = $date;
  $range_to = $date;
} elseif ($type === "weekly") {
  $ts = strtotime($date);
  $monday = date("Y-m-d", strtotime("monday this week", $ts));
  $sunday = date("Y-m-d", strtotime("sunday this week", $ts));
  $range_from = $monday; $range_to = $sunday;
} elseif ($type === "semester") {
  // semester: Jan-Jun or Jul-Dec based on selected date
  $m = (int)date("n", strtotime($date));
  if ($m <= 6) { $range_from = date("Y-01-01", strtotime($date)); $range_to = date("Y-06-30", strtotime($date)); }
  else { $range_from = date("Y-07-01", strtotime($date)); $range_to = date("Y-12-31", strtotime($date)); }
} else { // yearly
  $range_from = $year . "-01-01";
  $range_to = $year . "-12-31";
}

$where = "";
$params = [$range_from, $range_to, $range_from, $range_to];
$types = "ssss";

if ($class_id !== "") { $where .= " AND u.class_id=?"; $types .= "i"; $params[] = (int)$class_id; }
if ($major_id !== "") { $where .= " AND c.major_id=?"; $types .= "i"; $params[] = (int)$major_id; }

$sql = "SELECT u.id,u.full_name,u.role,c.name AS class_name,m.name AS major_name,
  COUNT(a.id) present_days,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) late_count,
  SUM(CASE WHEN a.status_out='EARLY' THEN 1 ELSE 0 END) early_count,
  (SELECT COUNT(*) FROM leave_requests lr WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN majors m ON m.id=c.major_id
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.status='ACTIVE' AND u.role IN ('SISWA','GURU','KEPSEK','YAYASAN') $where
  GROUP BY u.id
  ORDER BY u.role, c.name, u.full_name";

$stmt = $mysqli->prepare($sql);
stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $range_from, $range_to, $ay_id);
foreach ($rows as &$rr) {
  $rr["absent_count"] = count_absent_without_excuse($workdays, $rr["present_days"], $rr["leave_count"]);
}
unset($rr);

require_once __DIR__ . "/../includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-bar-chart me-1"></i>Rekap Absensi</h5>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-success" href="reports_export.php?type=<?= e($type) ?>&date=<?= e($date) ?>&year=<?= e($year) ?>&class_id=<?= e($class_id) ?>&major_id=<?= e($major_id) ?>"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Print</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" target="_blank" href="../students_rekap_print.php?group=student&from=<?= e($range_from) ?>&to=<?= e($range_to) ?>&class_id=<?= e($class_id) ?>&major_id=<?= e($major_id) ?>">Siswa - Per Siswa</a></li>
          <li><a class="dropdown-item" target="_blank" href="../students_rekap_print.php?group=class&from=<?= e($range_from) ?>&to=<?= e($range_to) ?>&major_id=<?= e($major_id) ?>">Siswa - Per Kelas</a></li>
          <li><a class="dropdown-item" target="_blank" href="../students_rekap_print.php?group=major&from=<?= e($range_from) ?>&to=<?= e($range_to) ?>">Siswa - Per Jurusan</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" target="_blank" href="../teachers_rekap_print.php?from=<?= e($range_from) ?>&to=<?= e($range_to) ?>">Guru - Rekap Semua</a></li>
        </ul>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
    </div>
  </div>

  <form class="row g-2 mt-2">
    <div class="col-12 col-md-3">
      <label class="form-label small">Jenis</label>
      <select class="form-select" name="type">
        <option value="daily" <?= $type==="daily"?"selected":"" ?>>Per Hari</option>
        <option value="weekly" <?= $type==="weekly"?"selected":"" ?>>Per Minggu</option>
        <option value="semester" <?= $type==="semester"?"selected":"" ?>>Per Semester</option>
        <option value="yearly" <?= $type==="yearly"?"selected":"" ?>>Per Tahun</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Tanggal (acuan)</label>
      <input type="date" class="form-control" name="date" value="<?= e($date) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small">Tahun (rekap tahunan)</label>
      <input type="number" class="form-control" name="year" value="<?= e($year) ?>">
    </div>
    <div class="col-12 col-md-2">
      <label class="form-label small">Kelas</label>
      <select class="form-select" name="class_id">
        <option value="">-- semua --</option>
        <?php foreach($classes as $c): ?>
          <option value="<?= (int)$c["id"] ?>" <?= ($class_id==(string)$c["id"])?"selected":"" ?>><?= e($c["name"]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-2">
      <label class="form-label small">Jurusan</label>
      <select class="form-select" name="major_id">
        <option value="">-- semua --</option>
        <?php foreach($majors as $m): ?>
          <option value="<?= (int)$m["id"] ?>" <?= ($major_id==(string)$m["id"])?"selected":"" ?>><?= e($m["name"]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 d-grid">
      <button class="btn btn-primary mt-2" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
    </div>
  </form>

  <div class="mt-3 small text-secondary">
    Rentang: <b><?= e($range_from) ?></b> s.d. <b><?= e($range_to) ?></b>
  </div>

  <div class="table-responsive mt-2">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Role</th>
          <th>Kelas</th>
          <th>Jurusan</th>
          <th class="text-center">Hadir</th>
          <th class="text-center">Telat</th>
          <th class="text-center">Ijin</th>
          <th class="text-center">Tanpa Ket.</th>
          <th class="text-center">Pulang Awal</th>
          <th class="text-end">Detail</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= e($r["full_name"]) ?></td>
            <td><span class="badge bg-secondary"><?= e(role_label($r["role"])) ?></span></td>
            <td><?= e($r["class_name"]) ?></td>
            <td><?= e($r["major_name"]) ?></td>
            <td class="text-center"><?= (int)$r["present_days"] ?></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["late_count"]>0)?"danger":"secondary" ?>"><?= (int)$r["late_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["leave_count"]>0)?"warning text-dark":"secondary" ?>"><?= (int)$r["leave_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["absent_count"]>0)?"danger":"secondary" ?>"><?= (int)$r["absent_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["early_count"]>0)?"warning text-dark":"secondary" ?>"><?= (int)$r["early_count"] ?></span></td>
            <td class="text-end">
              <?php if ($r["role"]==="SISWA"): ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= $BASE_URL ?>/student_detail.php?id=<?= (int)$r["id"] ?>&from=<?= e($range_from) ?>&to=<?= e($range_to) ?>"><i class="bi bi-search me-1"></i>Detail</a>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= $BASE_URL ?>/staff_detail.php?id=<?= (int)$r["id"] ?>&from=<?= e($range_from) ?>&to=<?= e($range_to) ?>"><i class="bi bi-search me-1"></i>Detail</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
