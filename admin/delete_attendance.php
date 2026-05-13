<?php
$title = "Hapus Record Absen";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

if (isset($_GET["delete"])) {
  $id = (int)$_GET["delete"];
  $stmt = $mysqli->prepare("DELETE FROM attendance WHERE id=?");
  $stmt->bind_param("i",$id);
  $stmt->execute();
  flash_set("success","Record absensi dihapus.");
  redirect("/admin/delete_attendance.php");
}

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$stmt = $mysqli->prepare("SELECT a.id,a.att_date,a.checkin_at,a.checkout_at,a.status_in,a.status_out,u.full_name,u.role
  FROM attendance a
  JOIN users u ON u.id=a.user_id
  WHERE a.att_date BETWEEN ? AND ?
  ORDER BY a.att_date DESC, a.id DESC
  LIMIT 200");
$stmt->bind_param("ss",$from,$to);
$stmt->execute();
$res = $stmt->get_result();
$rows=[];
while($r=$res->fetch_assoc()) $rows[]=$r;

require_once __DIR__ . "/../includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-trash me-1"></i>Hapus Record Absensi</h5>
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
          <th>Tanggal</th>
          <th>Nama</th>
          <th>Role</th>
          <th>Masuk</th>
          <th>Pulang</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= e($r["att_date"]) ?></td>
            <td><?= e($r["full_name"]) ?></td>
            <td><span class="badge bg-secondary"><?= e(role_label($r["role"])) ?></span></td>
            <td><?= $r["checkin_at"] ? e(date("H:i", strtotime($r["checkin_at"]))) : "-" ?> <?= $r["status_in"]? "<span class='badge bg-".($r["status_in"]==="LATE"?"danger":"success")." ms-1'>".e(att_code_label($r["status_in"]))."</span>":"" ?></td>
            <td><?= $r["checkout_at"] ? e(date("H:i", strtotime($r["checkout_at"]))) : "-" ?> <?= $r["status_out"]? "<span class='badge bg-".($r["status_out"]==="EARLY"?"warning text-dark":"success")." ms-1'>".e(att_code_label($r["status_out"]))."</span>":"" ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-danger" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&delete=<?= (int)$r["id"] ?>" onclick="return confirm('Hapus record ini?')">
                Hapus
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($rows)===0): ?>
          <tr><td colspan="6" class="text-secondary">Tidak ada data.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="text-secondary small mt-2">Menampilkan maksimal 200 record terakhir pada rentang tanggal.</div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
