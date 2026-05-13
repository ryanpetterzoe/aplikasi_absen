<?php
$title = "Approval Ijin";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();
require_role(["GURU","KEPSEK"]);

$note = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $leave_id = (int)($_POST["leave_id"] ?? 0);
  $action = $_POST["action"] ?? "";
  $note = trim($_POST["decision_note"] ?? "");

  if (!in_array($action, ["APPROVED","REJECTED"], true) || $leave_id <= 0) {
    flash_set("warning", "Aksi tidak valid.");
    redirect("/leave_approvals.php");
  }

  $stmt = $mysqli->prepare("SELECT lr.id, lr.user_id, lr.leave_date, lr.reason, lr.description, lr.status,
      u.full_name, u.role, u.class_id, c.name AS class_name, c.homeroom_teacher_id
    FROM leave_requests lr
    JOIN users u ON u.id=lr.user_id
    LEFT JOIN classes c ON c.id=u.class_id
    WHERE lr.id=? LIMIT 1");
  $stmt->bind_param("i", $leave_id);
  $stmt->execute();

  if ($info) {
    $msg = ($action === "APPROVED")
      ? ("Ijin tanggal " . $info["leave_date"] . " disetujui (" . $info["reason"] . ").")
      : ("Ijin tanggal " . $info["leave_date"] . " ditolak (" . $info["reason"] . ").");
    add_notification($mysqli, (int)$info["user_id"], "Status Ijin", $msg);
  }

  $req = $stmt->get_result()->fetch_assoc();

  if (!$req) {
    flash_set("warning", "Data ijin tidak ditemukan.");
    redirect("/leave_approvals.php");
  }

  // Only pending can be decided
  if ($req["status"] !== "PENDING") {
    flash_set("warning", "Ijin ini sudah diproses.");
    redirect("/leave_approvals.php");
  }

  $allowed = false;
  if ($u["role"] === "GURU") {
    // Semua guru bisa approve ijin siswa
    if ($req["role"] === "SISWA") $allowed = true;
  } elseif ($u["role"] === "KEPSEK") {
    // Kepsek bisa approve guru & siswa, dan bisa approve dirinya sendiri
    if (in_array($req["role"], ["SISWA","GURU","YAYASAN","KEPSEK"], true)) {
      if ($req["role"] !== "KEPSEK" || (int)$req["user_id"] === (int)$u["id"]) $allowed = true;
      // Kepsek lain: tidak diizinkan (sesuai permintaan: approve dirinya sendiri)
    }
  }



// ambil info pemohon untuk notifikasi
$stInfo = $mysqli->prepare("SELECT user_id, leave_date, reason FROM leave_requests WHERE id=? LIMIT 1");
$stInfo->bind_param("i", $leave_id);
$stInfo->execute();
$info = $stInfo->get_result()->fetch_assoc();

if (!$allowed) {
    flash_set("warning", "Anda tidak berhak memproses ijin ini.");
    redirect("/leave_approvals.php");
  }

  $stmt = $mysqli->prepare("UPDATE leave_requests SET status=?, approver_id=?, decided_at=NOW(), decision_note=? WHERE id=?");
  $stmt->bind_param("sisi", $action, $u["id"], $note, $leave_id);
  $stmt->execute();

  flash_set("success", ($action === "APPROVED" ? "Ijin disetujui." : "Ijin ditolak (akan dihitung tanpa keterangan)."));
  redirect("/leave_approvals.php");
}

// List pending requests
$pending = [];
if ($u["role"] === "GURU") {
  $stmt = $mysqli->prepare("SELECT lr.id, lr.leave_date, lr.reason, lr.description,
      su.full_name, su.nisn, c.name AS class_name
    FROM leave_requests lr
    JOIN users su ON su.id=lr.user_id
    LEFT JOIN classes c ON c.id=su.class_id
    WHERE lr.status='PENDING' AND su.role='SISWA'
    ORDER BY lr.leave_date DESC, lr.id DESC");
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $pending[] = $row;
} else { // KEPSEK
  $stmt = $mysqli->prepare("SELECT lr.id, lr.leave_date, lr.reason, lr.description,
      u.full_name, u.role, u.employee_no, u.nisn, c.name AS class_name
    FROM leave_requests lr
    JOIN users u ON u.id=lr.user_id
    LEFT JOIN classes c ON c.id=u.class_id
    WHERE lr.status='PENDING' AND u.role IN ('SISWA','GURU','YAYASAN','KEPSEK')
    ORDER BY lr.leave_date DESC, lr.id DESC");
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $pending[] = $row;
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-check2-circle me-1"></i>Approval Ijin</h5>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-success" href="leave_approvals_export.php"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
      <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
    </div>
  </div>

  <div class="text-secondary small mt-2">
    <?php if ($u["role"] === "GURU"): ?>
      Menampilkan ijin siswa untuk kelas yang Anda wali-kelas.
    <?php else: ?>
      Menampilkan ijin yang menunggu persetujuan (siswa/guru/yayasan). Kepsek hanya bisa approve ijin Kepsek miliknya sendiri.
    <?php endif; ?>
  </div>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Nama</th>
          <th>Role</th>
          <th>Kelas</th>
          <th>Alasan</th>
          <th>Keterangan</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($pending) === 0): ?>
          <tr><td colspan="7" class="text-center text-secondary">Tidak ada ijin yang menunggu persetujuan.</td></tr>
        <?php else: ?>
          <?php foreach($pending as $p): ?>
            <tr>
              <td><?= e($p["leave_date"]) ?></td>
              <td><?= e($p["full_name"]) ?></td>
              <td><span class="badge bg-secondary"><?= e($p["role"] ?? "SISWA") ?></span></td>
              <td><?= e($p["class_name"] ?? "-") ?></td>
              <td><?= e($p["reason"]) ?></td>
              <td class="text-secondary small"><?= e($p["description"]) ?></td>
              <td class="text-end">
                <form method="post" class="d-inline">
                  <input type="hidden" name="leave_id" value="<?= (int)$p["id"] ?>">
                  <input type="hidden" name="action" value="APPROVED">
                  <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check-lg me-1"></i>Approve</button>
                </form>
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#rej<?= (int)$p["id"] ?>"><i class="bi bi-x-lg me-1"></i>Reject</button>
              </td>
            </tr>
            <tr class="collapse" id="rej<?= (int)$p["id"] ?>">
              <td colspan="7" class="bg-light">
                <form method="post" class="row g-2 align-items-center">
                  <input type="hidden" name="leave_id" value="<?= (int)$p["id"] ?>">
                  <input type="hidden" name="action" value="REJECTED">
                  <div class="col-12 col-md-9">
                    <input class="form-control form-control-sm" name="decision_note" placeholder="Catatan penolakan (opsional)" maxlength="255">
                  </div>
                  <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-sm btn-danger" type="submit">Konfirmasi Tolak</button>
                  </div>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
