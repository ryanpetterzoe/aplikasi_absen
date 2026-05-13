<?php
$title = "Naik Kelas / Lulus";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$classes = [];
$rc = $mysqli->query("SELECT id,name,grade FROM classes WHERE is_active=1 ORDER BY grade,name");
while ($rc && $r = $rc->fetch_assoc()) $classes[] = $r;

$source_class = (int)($_GET["source_class"] ?? 0);

if (isset($_POST["promote"])) {
  $source = (int)($_POST["source_class"] ?? 0);
  $target = (int)($_POST["target_class"] ?? 0);
  if ($source>0 && $target>0) {
    $stmt = $mysqli->prepare("UPDATE users SET class_id=? WHERE role='SISWA' AND status='ACTIVE' AND is_alumni=0 AND class_id=?");
    $stmt->bind_param("ii", $target, $source);
    $stmt->execute();
    flash_set("success","Siswa dipindahkan ke kelas tujuan.");
  }
  redirect("/admin/promote.php?source_class=".$source);
}

if (isset($_POST["graduate"])) {
  $source = (int)($_POST["source_class"] ?? 0);
  if ($source>0) {
    $stmt = $mysqli->prepare("UPDATE users SET is_alumni=1, class_id=NULL WHERE role='SISWA' AND status='ACTIVE' AND class_id=?");
    $stmt->bind_param("i", $source);
    $stmt->execute();
    flash_set("success","Siswa pada kelas tersebut ditandai LULUS (alumni).");
  }
  redirect("/admin/promote.php?source_class=".$source);
}

$students = [];
if ($source_class > 0) {
  $stmt = $mysqli->prepare("SELECT id,full_name,nisn FROM users WHERE role='SISWA' AND status='ACTIVE' AND is_alumni=0 AND class_id=? ORDER BY full_name");
  $stmt->bind_param("i",$source_class);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $students[] = $r;
}

require_once __DIR__ . "/../includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="d-flex align-items-center justify-content-between">
    <h5 class="fw-semibold mb-0"><i class="bi bi-arrow-up-right-circle me-1"></i>Naik Kelas / Lulus</h5>
    <a class="btn btn-sm btn-outline-secondary" href="dashboard.php">Kembali</a>
  </div>

  <form class="row g-2 mt-3">
    <div class="col-12 col-md-6">
      <label class="form-label">Pilih Kelas Asal</label>
      <select class="form-select" name="source_class" onchange="this.form.submit()">
        <option value="0">-- pilih --</option>
        <?php foreach($classes as $c): ?>
          <option value="<?= (int)$c["id"] ?>" <?= ($source_class===(int)$c["id"])?"selected":"" ?>><?= e($c["name"]) ?> (<?= (int)$c["grade"] ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if ($source_class>0): ?>
    <hr>
    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <div class="fw-semibold mb-2">Naik / Pindah Kelas</div>
        <form method="post" class="vstack gap-2">
          <input type="hidden" name="source_class" value="<?= (int)$source_class ?>">
          <label class="form-label">Kelas Tujuan</label>
          <select class="form-select" name="target_class" required>
            <option value="">-- pilih --</option>
            <?php foreach($classes as $c): ?>
              <?php if ((int)$c["id"] !== (int)$source_class): ?>
                <option value="<?= (int)$c["id"] ?>"><?= e($c["name"]) ?> (<?= (int)$c["grade"] ?>)</option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" name="promote" value="1" onclick="return confirm('Pindahkan semua siswa ke kelas tujuan?')">Proses Pindah</button>
        </form>
        <div class="text-secondary small mt-2">Catatan: Ini memindahkan semua siswa dalam kelas asal ke kelas tujuan (bulk).</div>
      </div>

      <div class="col-12 col-lg-6">
        <div class="fw-semibold mb-2">Luluskan (Alumni)</div>
        <form method="post">
          <input type="hidden" name="source_class" value="<?= (int)$source_class ?>">
          <button class="btn btn-outline-danger" name="graduate" value="1" onclick="return confirm('Tandai semua siswa di kelas ini sebagai alumni?')">
            Tandai Lulus
          </button>
        </form>
        <div class="text-secondary small mt-2">Siswa alumni tidak muncul di daftar aktif dan tidak perlu input ulang saat pergantian tahun.</div>
      </div>
    </div>

    <hr>
    <div class="fw-semibold mb-2">Daftar Siswa Kelas Ini (<?= count($students) ?>)</div>
    <div class="table-responsive">
      <table class="table table-sm">
        <thead><tr><th>Nama</th><th>NISN</th></tr></thead>
        <tbody>
          <?php foreach($students as $s): ?>
            <tr><td><?= e($s["full_name"]) ?></td><td><?= e($s["nisn"]) ?></td></tr>
          <?php endforeach; ?>
          <?php if (count($students)===0): ?>
            <tr><td colspan="2" class="text-secondary">Tidak ada siswa.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
