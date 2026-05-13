<?php
$title = "Absensi Teman";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();
require_role("SISWA");

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

if (!$u["class_id"]) { flash_set("warning","Kelas Anda belum diset."); redirect("/dashboard.php"); }

$sql = "SELECT u.id,u.full_name,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  COUNT(a.id) AS present_days,
  (SELECT COUNT(*) FROM leave_requests lr WHERE lr.user_id=u.id AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.role='SISWA' AND u.status='ACTIVE' AND u.class_id=? AND u.is_alumni=0
  GROUP BY u.id ORDER BY u.full_name";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssssi", $from, $to, $from, $to, $u["class_id"]);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) $rows[] = $row;

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-people me-1"></i>Absensi Teman (Satu Kelas)</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
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
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= e($r["full_name"]) ?><?= ((int)$r["id"]===(int)$u["id"]) ? " <span class='badge bg-secondary'>Saya</span>" : "" ?></td>
            <td class="text-center"><?= (int)$r["present_days"] ?></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["late_count"]>0)?"danger":"secondary" ?>"><?= (int)$r["late_count"] ?></span></td>
            <td class="text-center"><span class="badge bg-<?= ((int)$r["leave_count"]>0)?"warning text-dark":"secondary" ?>"><?= (int)$r["leave_count"] ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
