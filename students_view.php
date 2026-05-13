<?php
$title = "Absensi Siswa";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();
require_role(["GURU","KEPSEK","YAYASAN"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");
$class_id = $_GET["class_id"] ?? "";

$classes = [];
$rc = $mysqli->query("SELECT id,name FROM classes WHERE is_active=1 ORDER BY grade,name");
while ($rc && $row = $rc->fetch_assoc()) $classes[] = $row;

$where = "";
$params = [];
$types = "ssss";
$params = [$from, $to, $from, $to];

if ($class_id !== "") {
  $where = "AND u.class_id=?";
  $types .= "i";
  $params[] = (int)$class_id;
} else {
  // Guru hanya boleh lihat siswa (semua kelas) -> boleh; jika mau dibatasi wali kelas, bisa ditambah logic
  // Kepsek/Yayasan: boleh semua
}

$sql = "SELECT u.id,u.full_name,u.nisn,c.name AS class_name,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  COUNT(DISTINCT a.att_date) AS present_days,
  (SELECT COUNT(*) FROM leave_requests lr WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.role='SISWA' AND u.status='ACTIVE' AND u.is_alumni=0 $where
  GROUP BY u.id
  ORDER BY c.name,u.full_name";

$stmt = $mysqli->prepare($sql);
stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;

$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);
foreach ($rows as &$rr) {
  $rr["absent_count"] = count_absent_without_excuse($workdays, $rr["present_days"], $rr["leave_count"]);
}
unset($rr);

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-people me-1"></i>Absensi Siswa</h5>
    <div class="d-flex gap-2">
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="students_rekap_export.php?group=student&from=<?= e($from) ?>&to=<?= e($to) ?>&class_id=<?= e($class_id) ?>">Per Siswa</a></li>
          <li><a class="dropdown-item" href="students_rekap_export.php?group=class&from=<?= e($from) ?>&to=<?= e($to) ?>&class_id=<?= e($class_id) ?>">Per Kelas</a></li>
          <li><a class="dropdown-item" href="students_rekap_export.php?group=major&from=<?= e($from) ?>&to=<?= e($to) ?>">Per Jurusan</a></li>
        </ul>
      </div>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Print</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" target="_blank" href="students_rekap_print.php?group=student&from=<?= e($from) ?>&to=<?= e($to) ?>&class_id=<?= e($class_id) ?>">Per Siswa</a></li>
          <li><a class="dropdown-item" target="_blank" href="students_rekap_print.php?group=class&from=<?= e($from) ?>&to=<?= e($to) ?>&class_id=<?= e($class_id) ?>">Per Kelas</a></li>
          <li><a class="dropdown-item" target="_blank" href="students_rekap_print.php?group=major&from=<?= e($from) ?>&to=<?= e($to) ?>">Per Jurusan</a></li>
        </ul>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
    </div>
  </div>

  <form class="row g-2 mt-2">
    <div class="col-6 col-md-3">
      <label class="form-label small">Dari</label>
      <input type="date" class="form-control" name="from" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small">Sampai</label>
      <input type="date" class="form-control" name="to" value="<?= e($to) ?>">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label small">Kelas</label>
      <select class="form-select" name="class_id">
        <option value="">-- semua --</option>
        <?php foreach($classes as $c): ?>
          <option value="<?= (int)$c["id"] ?>" <?= ($class_id==(string)$c["id"])?"selected":"" ?>><?= e($c["name"]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-2 d-grid align-items-end">
      <button class="btn btn-primary mt-4" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
    </div>
  </form>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Kelas</th>
          <th class="text-center">Hadir</th>
          <th class="text-center">Telat</th>
          <th class="text-center">Ijin</th>
          <th class="text-center">Tanpa Ket.</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= e($r["full_name"]) ?><div class="small text-secondary">NISN: <?= e($r["nisn"]) ?></div></td>
            <td><?= e($r["class_name"]) ?></td>
            <td class="text-center"><?= (int)$r["present_days"] ?></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["late_count"]>0)?"danger":"secondary" ?>"><?= (int)$r["late_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["leave_count"]>0)?"warning text-dark":"secondary" ?>"><?= (int)$r["leave_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["absent_count"]>0?"danger":"secondary") ?>"><?= (int)$r["absent_count"] ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="student_detail.php?id=<?= (int)$r["id"] ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><i class="bi bi-search me-1"></i>Detail</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
