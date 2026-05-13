<?php
$title = "Ijin Tidak Berangkat";
require_once __DIR__ . "/includes/functions.php";
require_login();
$u = current_user();

$today = date("Y-m-d");
$att = attendance_today($mysqli, $u["id"], $today);
if ($att && ($att["checkin_at"] || $att["checkout_at"])) {
  flash_set("warning", "Tidak bisa ijin karena sudah ada absensi hari ini.");
  redirect("/dashboard.php");
}

$reasons = [];
if ($u["role"] === "SISWA") {
  $reasons = ["Sakit","Acara Sekolah","Keperluan Lainnya"];
} else {
  $reasons = ["Sakit","Dinas Luar","Alasan Lainnya"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $reason = $_POST["reason"] ?? "";
  $desc = trim($_POST["description"] ?? "");

  if (!in_array($reason, $reasons, true)) {
    flash_set("warning", "Alasan tidak valid.");
    redirect("/leave.php");
  }

  // Batasi: 1x ijin per hari (untuk semua role)
  $today = date("Y-m-d");
  $stDup = $mysqli->prepare("SELECT COUNT(*) c FROM leave_requests WHERE user_id=? AND DATE(leave_date)=?");
  $stDup->bind_param("is", $u["id"], $today);
  $stDup->execute();
  $dup = $stDup->get_result()->fetch_assoc();
  if ((int)($dup["c"] ?? 0) > 0) {
    flash_set("warning", "Ijin sudah pernah diajukan untuk hari ini. Maksimal 1x per hari.");
    redirect("/leave.php");
  }

  // prevent duplicate
  
// Tentukan approver & status
$approver_id = null;
$status = "PENDING";
$decided_at = null;

if ($u["role"] === "SISWA") {
  // default: wali kelas (jika ada). Jika tidak ada, tetap PENDING (bisa diproses oleh Kepsek)
  if (!empty($u["class_id"])) {
    $st = $mysqli->prepare("SELECT homeroom_teacher_id FROM classes WHERE id=? LIMIT 1");
    $st->bind_param("i", $u["class_id"]);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!empty($row["homeroom_teacher_id"])) $approver_id = (int)$row["homeroom_teacher_id"];
  }
} elseif ($u["role"] === "GURU" || $u["role"] === "YAYASAN") {
  // Guru/Yayasan: approval oleh Kepsek
  $st = $mysqli->prepare("SELECT id FROM users WHERE role='KEPSEK' AND status='ACTIVE' ORDER BY id ASC LIMIT 1");
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  if ($row) $approver_id = (int)$row["id"];
} elseif ($u["role"] === "KEPSEK") {
  // Kepsek boleh approve diri sendiri (auto-approved)
  $status = "APPROVED";
  $approver_id = (int)$u["id"];
  $decided_at = date("Y-m-d H:i:s");
}

$stmt = $mysqli->prepare("INSERT INTO leave_requests (user_id, leave_date, reason, description, status, approver_id, decided_at, decision_note)
  VALUES (?,?,?,?,?,?,?,NULL)");
$stmt->bind_param("issssis", $u["id"], $today, $reason, $desc, $status, $approver_id, $decided_at);
$stmt->execute();

if ($status === "APPROVED") {
  flash_set("success", "Ijin disetujui untuk tanggal " . $today);
} else {
  flash_set("success", "Ijin terkirim. Menunggu persetujuan.");
}
redirect("/dashboard.php");
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-file-earmark-medical me-1"></i>Ijin Tidak Berangkat</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>
  <div class="text-secondary small mt-1">Jika ijin, maka hari ini tidak ada absen pulang. (Maksimal 1x ijin per hari)</div>

  <form method="post" class="mt-3 vstack gap-2">
    <div>
      <label class="form-label">Tanggal</label>
      <input class="form-control" value="<?= e($today) ?>" disabled>
    </div>
    <div>
      <label class="form-label">Alasan</label>
      <select class="form-select" name="reason" required>
        <option value="">-- pilih --</option>
        <?php foreach($reasons as $r): ?>
          <option value="<?= e($r) ?>"><?= e($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="form-label">Keterangan (opsional)</label>
      <input class="form-control" name="description" placeholder="Tambahan keterangan...">
    </div>
    <button class="btn btn-primary btn-lg" type="submit">Simpan Ijin</button>
  </form>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
