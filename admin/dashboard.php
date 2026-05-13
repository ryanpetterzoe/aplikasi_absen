<?php
$title = "Admin Dashboard";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$pending = $mysqli->query("SELECT COUNT(*) c FROM users WHERE status='PENDING'")->fetch_assoc()["c"] ?? 0;
$students = $mysqli->query("SELECT COUNT(*) c FROM users WHERE role='SISWA' AND status='ACTIVE' AND is_alumni=0")->fetch_assoc()["c"] ?? 0;
$teachers = $mysqli->query("SELECT COUNT(*) c FROM users WHERE role IN ('GURU','KEPSEK','YAYASAN') AND status='ACTIVE'")->fetch_assoc()["c"] ?? 0;

require_once __DIR__ . "/../includes/header.php";
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft p-3">
      <div class="fw-semibold fs-5"><i class="bi bi-speedometer2 me-1"></i>Admin Dashboard</div>
      <div class="text-secondary small">Kelola data sekolah, approval register, aturan jam kerja, rekap.</div>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="card tile p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-secondary small">Register Pending</div>
          <div class="fs-4 fw-semibold"><?= (int)$pending ?></div>
        </div>
        <i class="bi bi-person-check text-primary"></i>
      </div>
      <a class="btn btn-primary mt-2 w-100" href="users_pending.php">Kelola Approval</a>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="card tile p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-secondary small">Siswa Aktif</div>
          <div class="fs-4 fw-semibold"><?= (int)$students ?></div>
        </div>
        <i class="bi bi-people text-primary"></i>
      </div>
      <a class="btn btn-outline-primary mt-2 w-100" href="master_users.php?role=SISWA">Data Siswa</a>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="card tile p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-secondary small">Guru/Kepsek/Yayasan</div>
          <div class="fs-4 fw-semibold"><?= (int)$teachers ?></div>
        </div>
        <i class="bi bi-person-badge text-primary"></i>
      </div>
      <a class="btn btn-outline-primary mt-2 w-100" href="master_users.php?role=GURU">Data Guru</a>
    </div>
  </div>

  <div class="col-12">
    <div class="card tile p-3">
      <div class="row g-2">
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-primary" href="school_profile.php"><i class="bi bi-building me-1"></i>Identitas Sekolah + Logo</a>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-primary" href="master_data.php"><i class="bi bi-database me-1"></i>Master (Tahun/Kelas/Jurusan)</a>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-primary" href="work_rules.php"><i class="bi bi-clock me-1"></i>Jam Kerja & Toleransi</a>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-primary" href="reports.php"><i class="bi bi-bar-chart me-1"></i>Rekap Absensi</a>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-primary" href="promote.php"><i class="bi bi-arrow-up-right-circle me-1"></i>Naik Kelas / Lulus</a>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-primary" href="db_tools.php"><i class="bi bi-hdd-network me-1"></i>Backup / Restore DB</a>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <a class="btn btn-outline-danger" href="delete_attendance.php"><i class="bi bi-trash me-1"></i>Hapus Record Absen</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="card tile p-3">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="text-secondary small">Pengaturan</div>
          <div class="fs-5 fw-semibold">Aplikasi</div>
        </div>
        <i class="bi bi-sliders text-primary"></i>
      </div>
      <a class="btn btn-outline-primary mt-2 w-100" href="app_settings.php">Buka Pengaturan</a>
    </div>
  </div>

</div>
<div class="mt-3">
  <a class="btn btn-outline-primary" href="classes.php"><i class="bi bi-easel2 me-1"></i>Kelola Kelas (Edit/Hapus)</a>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
