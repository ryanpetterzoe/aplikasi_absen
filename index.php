<?php
$title = "Beranda";
require_once __DIR__ . "/includes/functions.php";

$u = current_user();
if ($u) {
  // role redirect
  if ($u["role"] === "ADMIN") redirect("/admin/dashboard.php");
  redirect("/dashboard.php");
}

require_once __DIR__ . "/includes/header.php";
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card card-soft p-3">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:52px;height:52px;">
          <i class="bi bi-shield-check"></i>
        </div>
        <div>
          <div class="fw-semibold fs-5">Absensi Sekolah</div>
          <div class="text-secondary">Web mobile friendly • Kamera + GPS • Modern</div>
        </div>
      </div>
      <hr>
      <div class="d-grid gap-2 d-md-flex">
        <a class="btn btn-primary btn-lg" href="login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Login</a>
        <a class="btn btn-outline-primary btn-lg" href="register.php"><i class="bi bi-person-plus me-1"></i>Register</a>
      </div>
      <div class="mt-3 footer-note">Catatan: Untuk izin kamera & lokasi, gunakan browser modern (Chrome/Edge) di HP.</div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . "/includes/footer.php"; ?>
