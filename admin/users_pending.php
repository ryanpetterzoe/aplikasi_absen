<?php
$title = "Approval Register";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

if (isset($_GET["approve"])) {
  $id = (int)$_GET["approve"];
  $stmt = $mysqli->prepare("UPDATE users SET status='ACTIVE' WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  flash_set("success","Akun disetujui.");
  redirect("/admin/users_pending.php");
}
if (isset($_GET["reject"])) {
  $id = (int)$_GET["reject"];
  $stmt = $mysqli->prepare("UPDATE users SET status='REJECTED' WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  flash_set("warning","Akun ditolak.");
  redirect("/admin/users_pending.php");
}

$res = $mysqli->query("SELECT u.*, c.name AS class_name, ay.name AS year_name FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN academic_years ay ON ay.id=u.academic_year_id
  WHERE u.status='PENDING' ORDER BY u.created_at DESC");

$rows = [];
while ($res && $r = $res->fetch_assoc()) $rows[] = $r;

require_once __DIR__ . "/../includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-person-check me-1"></i>Approval Register</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Nama</th>
          <th>Role</th>
          <th>Info</th>
          <th>Tanggal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows)===0): ?>
          <tr><td colspan="5" class="text-secondary">Tidak ada data pending.</td></tr>
        <?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?= e($r["full_name"]) ?><div class="small text-secondary">@<?= e($r["username"]) ?></div></td>
            <td><span class="badge bg-secondary"><?= e(role_label($r["role"])) ?></span></td>
            <td class="small">
              <?= $r["role"]==="SISWA" ? "NISN: ".e($r["nisn"]) : "NIP: ".e($r["employee_no"]) ?><br>
              Kelas: <?= e($r["class_name"]) ?><br>
              Tahun: <?= e($r["year_name"]) ?>
            </td>
            <td class="small"><?= e($r["created_at"]) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-primary" href="?approve=<?= (int)$r["id"] ?>">Approve</a>
              <a class="btn btn-sm btn-outline-danger" href="?reject=<?= (int)$r["id"] ?>">Reject</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
