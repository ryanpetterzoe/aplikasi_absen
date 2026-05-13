<?php
$title = "Absensi Guru";
require_once __DIR__ . "/includes/functions.php";
require_login();
require_role(["KEPSEK","YAYASAN"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sql = "SELECT u.id,u.full_name,u.employee_no,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  COUNT(DISTINCT a.att_date) AS present_days,
  (SELECT COUNT(*) FROM leave_requests lr WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.role IN ('GURU','KEPSEK','YAYASAN') AND u.status='ACTIVE'
  GROUP BY u.id ORDER BY u.role,u.full_name";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssss", $from, $to, $from, $to);
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
    <h5 class="fw-semibold mb-0"><i class="bi bi-person-badge me-1"></i>Absensi Guru</h5>
    <div class="d-flex gap-2">
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="teachers_rekap_export.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Rekap Semua Guru</a></li>
          <?php if (!empty($rows)): ?>
            <li><hr class="dropdown-divider"></li>
            <?php foreach($rows as $rr): ?>
              <li><a class="dropdown-item" href="teachers_rekap_export.php?teacher_id=<?= (int)$rr["id"] ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">Detail: <?= e($rr["full_name"]) ?></a></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Print</button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" target="_blank" href="teachers_rekap_print.php?from=<?= e($from) ?>&to=<?= e($to) ?>">Rekap Semua Guru</a></li>
          <?php if (!empty($rows)): ?>
            <li><hr class="dropdown-divider"></li>
            <?php foreach($rows as $rr): ?>
              <li><a class="dropdown-item" target="_blank" href="teachers_rekap_print.php?teacher_id=<?= (int)$rr["id"] ?>&from=<?= e($from) ?>&to=<?= e($to) ?>">Detail: <?= e($rr["full_name"]) ?></a></li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
    </div>
  </div>

  <form class="row g-2 mt-2">
    <div class="col-6 col-md-4">
      <label class="form-label small">Dari</label>
      <input type="date" class="form-control" name="from" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label small">Sampai</label>
      <input type="date" class="form-control" name="to" value="<?= e($to) ?>">
    </div>
    <div class="col-12 col-md-4 d-grid align-items-end">
      <button class="btn btn-primary mt-4" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
    </div>
  </form>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Nama</th>
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
            <td><?= e($r["full_name"]) ?><div class="small text-secondary">NIP: <?= e($r["employee_no"]) ?></div></td>
            <td class="text-center"><?= (int)$r["present_days"] ?></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["late_count"]>0)?"danger":"secondary" ?>"><?= (int)$r["late_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["leave_count"]>0)?"warning text-dark":"secondary" ?>"><?= (int)$r["leave_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["absent_count"]>0)?"danger":"secondary" ?>"><?= (int)$r["absent_count"] ?></span></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-primary" href="staff_detail.php?id=<?= (int)$r["id"] ?>&from=<?= e($from) ?>&to=<?= e($to) ?>"><i class="bi bi-search me-1"></i>Detail</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
