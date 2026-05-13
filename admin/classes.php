<?php
$title = "Data Kelas";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

// fetch data
$sql = "SELECT c.*, 
               m.name AS major_name,
               y.name AS year_name,
               u.full_name AS homeroom_name
        FROM classes c
        LEFT JOIN majors m ON m.id=c.major_id
        LEFT JOIN academic_years y ON y.id=c.academic_year_id
        LEFT JOIN users u ON u.id=c.homeroom_teacher_id
        ORDER BY c.grade ASC, c.name ASC";
$res = $mysqli->query($sql);
$classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

require_once __DIR__ . "/../includes/header.php";
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="fw-semibold fs-4">Data Kelas</div>
    <div class="text-secondary small">Edit wali kelas & hapus kelas.</div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-success" href="classes_export.php"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    <a class="btn btn-outline-primary" href="master_data_import.php?type=classes"><i class="bi bi-file-earmark-arrow-up me-1"></i>Import Excel</a>
    <a class="btn btn-outline-secondary" href="master_data.php"><i class="bi bi-arrow-left me-1"></i>Master Data</a>
  </div>
</div>

<div class="card card-soft p-3">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Tingkat</th>
          <th>Nama Kelas</th>
          <th>Jurusan</th>
          <th>Wali Kelas</th>
          <th>Tahun</th>
          <th>Status</th>
          <th class="text-end">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($classes as $c): ?>
        <tr>
          <td class="fw-semibold"><?= (int)$c["grade"] ?></td>
          <td><?= e($c["name"]) ?></td>
          <td><?= e($c["major_name"] ?? "-") ?></td>
          <td><?= e($c["homeroom_name"] ?? "-") ?></td>
          <td><?= e($c["year_name"] ?? "-") ?></td>
          <td>
            <?php if ((int)($c["is_active"] ?? 1) === 1): ?>
              <span class="badge bg-success">Aktif</span>
            <?php else: ?>
              <span class="badge bg-secondary">Nonaktif</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="class_edit.php?id=<?= (int)$c["id"] ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
            <form method="post" action="class_delete.php" class="d-inline">
              <input type="hidden" name="id" value="<?= (int)$c["id"] ?>">
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus kelas ini? Siswa yang terkait akan dikosongkan kelasnya.');">
                <i class="bi bi-trash me-1"></i>Hapus
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
