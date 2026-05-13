<?php
$title = "Dashboard";
require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/auth.php";

require_login();
$u = current_user();
if ($u["role"] === "ADMIN") redirect("/admin/dashboard.php");

$today = date("Y-m-d");
$att = attendance_today($mysqli, $u["id"], $today);
$leave = has_leave_today($mysqli, $u["id"], $today);
$unread_notif = count_unread_notifications($mysqli, $u["id"]);
$notif_rows = get_unread_notifications($mysqli, $u["id"], 3);


require_once __DIR__ . "/includes/header.php";
?>
<div class="row g-3">
  <?php if ($unread_notif>0): ?>
    <div class="col-12">
      <div class="alert alert-info d-flex justify-content-between align-items-start mb-0">
        <div>
          <div class="fw-semibold"><i class="bi bi-bell me-1"></i>Ada <?= (int)$unread_notif ?> notifikasi baru</div>
          <?php foreach($notif_rows as $n): ?>
            <div class="small mt-1">• <?= e($n["message"]) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="ms-3">
          <a class="btn btn-sm btn-outline-primary" href="notifications.php">Lihat</a>
          <a class="btn btn-sm btn-outline-secondary" href="notifications.php?mark=all&back=dashboard.php">Tandai dibaca</a>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="col-12">
    <div class="card card-soft p-3">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <div class="fw-semibold fs-5">Dashboard</div>
          <div class="text-secondary small"><?= e(role_label($u["role"])) ?> • <?= e($u["username"]) ?></div>
        </div>
        <span class="badge bg-secondary"><?= e($today) ?></span>
      </div>

      <?php if ((int)$u["must_change_password"] === 1): ?>
        <div class="alert alert-warning mt-3 mb-0">Password Anda baru di-reset. Silakan <a href="profile.php">ganti password</a>.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card tile p-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-camera-fill text-primary"></i>
        <div class="fw-semibold">Absensi Hari Ini</div>
      </div>
      <div class="mt-2 small text-secondary">
        <?php if ($leave): ?>
          Status: <span class="badge bg-warning text-dark">IJIN</span>
        <?php else: ?>
          Masuk: <?= $att && $att["checkin_at"] ? e(date("H:i", strtotime($att["checkin_at"]))) : "-" ?> 
          <?= $att && $att["status_in"] ? "<span class='badge bg-" . ($att["status_in"]==="LATE"?"danger":"success") . " ms-1'>" . e(att_code_label($att["status_in"])) . "</span>" : "" ?>
          <br>
          Pulang: <?= $att && $att["checkout_at"] ? e(date("H:i", strtotime($att["checkout_at"]))) : "-" ?>
          <?= $att && $att["status_out"] ? "<span class='badge bg-" . ($att["status_out"]==="EARLY"?"warning text-dark":"success") . " ms-1'>" . e(att_code_label($att["status_out"])) . "</span>" : "" ?>
        <?php endif; ?>
      </div>

      <div class="d-grid gap-2 mt-3">
        <a class="btn btn-primary" href="attendance_checkin.php" <?= ($leave || ($att && $att["checkin_at"])) ? "disabled" : "" ?>><i class="bi bi-box-arrow-in-right me-1"></i>Absen Masuk</a>
        <a class="btn btn-outline-primary" href="attendance_checkout.php" <?= ($leave || !$att || !$att["checkin_at"] || ($att && $att["checkout_at"])) ? "disabled" : "" ?>><i class="bi bi-box-arrow-left me-1"></i>Absen Pulang</a>
        <a class="btn btn-outline-secondary" href="leave.php" <?= ($att && ($att["checkin_at"] || $att["checkout_at"])) ? "disabled" : "" ?>><i class="bi bi-file-earmark-medical me-1"></i>Ijin Tidak Berangkat</a>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="card tile p-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-primary"></i>
        <div class="fw-semibold">Menu</div>
      </div>

      <div class="list-group list-group-flush mt-2">
        <a class="list-group-item list-group-item-action" href="attendance_log.php"><i class="bi bi-journal-check me-2"></i>Log Absensi Saya</a>

        <?php if (in_array($u["role"], ["GURU","KEPSEK"], true)): ?>
          <a class="list-group-item list-group-item-action" href="leave_approvals.php"><i class="bi bi-check2-circle me-2"></i>Approval Ijin</a>
        <?php endif; ?>

        <?php if (in_array($u["role"], ["GURU","KEPSEK","YAYASAN"], true)): ?>
          <a class="list-group-item list-group-item-action" href="students_view.php"><i class="bi bi-people me-2"></i>Lihat Absensi Siswa</a>
          <?php if ($u["role"] === "GURU"): ?>
            <a class="list-group-item list-group-item-action" href="teacher_letter.php"><i class="bi bi-printer me-2"></i>Cetak Surat Rekap Siswa</a>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($u["role"] === "SISWA"): ?>
          <a class="list-group-item list-group-item-action" href="friends_view.php"><i class="bi bi-people me-2"></i>Lihat Absensi Teman</a>
        <?php endif; ?>

        <?php if (in_array($u["role"], ["KEPSEK","YAYASAN"], true)): ?>
          <a class="list-group-item list-group-item-action" href="teachers_view.php"><i class="bi bi-person-badge me-2"></i>Lihat Absensi Guru</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
