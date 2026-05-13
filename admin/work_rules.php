<?php
$title = "Hari Kerja, Jam Kerja & Libur";
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$year_id = get_active_academic_year_id($mysqli);
if (!$year_id) {
  flash_set("warning", "Buat dan aktifkan Tahun Pelajaran dulu di Master Data.");
  redirect("/admin/master_data.php");
}

$base = get_work_rules($mysqli);
if (!$base) {
  // buat default base rule jika belum ada
  $stmt = $mysqli->prepare("INSERT INTO work_rules (academic_year_id, checkin_time, checkout_time, late_tolerance_min) VALUES (?,?,?,?)");
  $checkin="07:00:00"; $checkout="15:00:00"; $tol=10;
  $stmt->bind_param("issi", $year_id, $checkin, $checkout, $tol);
  $stmt->execute();
  $base = get_work_rules($mysqli);
}

ensure_default_work_schedule($mysqli, $year_id, $base);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "save_schedule";

  if ($action === "save_schedule") {
    $tol = (int)($_POST["late_tolerance_min"] ?? ($base["late_tolerance_min"] ?? 10));

    // simpan base rules (toleransi + fallback jam default)
    $fallback_in = $_POST["fallback_checkin"] ?? ($base["checkin_time"] ?? "07:00:00");
    $fallback_out = $_POST["fallback_checkout"] ?? ($base["checkout_time"] ?? "15:00:00");

    $stmt = $mysqli->prepare("INSERT INTO work_rules (academic_year_id, checkin_time, checkout_time, late_tolerance_min) VALUES (?,?,?,?)");
    $stmt->bind_param("issi", $year_id, $fallback_in, $fallback_out, $tol);
    $stmt->execute();

    // update per hari
    $days = [
      1=>"Senin",2=>"Selasa",3=>"Rabu",4=>"Kamis",5=>"Jumat",6=>"Sabtu",7=>"Minggu"
    ];
    foreach ($days as $d=>$label) {
      $is_workday = isset($_POST["is_workday"][$d]) ? 1 : 0;
      $cin = $_POST["checkin_time"][$d] ?? $fallback_in;
      $cout = $_POST["checkout_time"][$d] ?? $fallback_out;

      // upsert
      $stmt = $mysqli->prepare("INSERT INTO work_schedule (academic_year_id, day_of_week, is_workday, checkin_time, checkout_time)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE is_workday=VALUES(is_workday), checkin_time=VALUES(checkin_time), checkout_time=VALUES(checkout_time)");
      $stmt->bind_param("iiiss", $year_id, $d, $is_workday, $cin, $cout);
      $stmt->execute();
    }

    flash_set("success", "Jadwal hari kerja & jam kerja tersimpan.");
    redirect("/admin/work_rules.php");
  }

  if ($action === "add_holiday") {
    $hdate = $_POST["holiday_date"] ?? "";
    $hname = trim($_POST["holiday_name"] ?? "");
    $scope = $_POST["holiday_scope"] ?? "year"; // year|global
    $ay = ($scope === "global") ? null : $year_id;

    if (!$hdate || !$hname) {
      flash_set("warning", "Tanggal dan nama libur wajib diisi.");
      redirect("/admin/work_rules.php");
    }

    if ($ay === null) {
      $stmt = $mysqli->prepare("INSERT IGNORE INTO holidays (academic_year_id, holiday_date, name) VALUES (NULL, ?, ?)");
      $stmt->bind_param("ss", $hdate, $hname);
    } else {
      $stmt = $mysqli->prepare("INSERT IGNORE INTO holidays (academic_year_id, holiday_date, name) VALUES (?, ?, ?)");
      $stmt->bind_param("iss", $ay, $hdate, $hname);
    }
    $stmt->execute();

    flash_set("success", "Hari libur ditambahkan.");
    redirect("/admin/work_rules.php");
  }

  if ($action === "delete_holiday") {
    $id = (int)($_POST["holiday_id"] ?? 0);
    if ($id) {
      $stmt = $mysqli->prepare("DELETE FROM holidays WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      flash_set("success", "Hari libur dihapus.");
    }
    redirect("/admin/work_rules.php");
  }
}

// load schedule rows
$stmt = $mysqli->prepare("SELECT * FROM work_schedule WHERE academic_year_id=? ORDER BY day_of_week ASC");
$stmt->bind_param("i", $year_id);
$stmt->execute();
$sched_rows = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $sched_rows[(int)$row["day_of_week"]] = $row;

$days = [
  1=>"Senin",2=>"Selasa",3=>"Rabu",4=>"Kamis",5=>"Jumat",6=>"Sabtu",7=>"Minggu"
];

// load holidays (global + year)
$stmt = $mysqli->prepare("SELECT * FROM holidays WHERE academic_year_id IS NULL OR academic_year_id=? ORDER BY holiday_date ASC, academic_year_id DESC");
$stmt->bind_param("i", $year_id);
$stmt->execute();
$holidays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$base = get_work_rules($mysqli);

require_once __DIR__ . "/../includes/header.php";
?>
<div class="card card-soft p-3">
  <div class="fw-semibold fs-5"><i class="bi bi-clock-history me-1"></i>Hari Kerja, Jam Kerja & Hari Libur</div>
  <div class="text-secondary small">Timezone aplikasi: <b>Asia/Jakarta (WIB)</b>. Atur hari kerja dan jam kerja per hari. Hari libur akan memblokir absensi masuk/pulang di tanggal tersebut.</div>

  <hr class="my-3">

  <form method="post" class="row g-2">
    <input type="hidden" name="action" value="save_schedule">

    <div class="col-12 col-md-4">
      <label class="form-label">Toleransi Telat (menit)</label>
      <input class="form-control" type="number" min="0" name="late_tolerance_min" value="<?= (int)($base["late_tolerance_min"] ?? 10) ?>">
      <div class="small text-secondary mt-1">Berlaku untuk semua hari kerja.</div>
    </div>

    <div class="col-12 col-md-4">
      <label class="form-label">Jam Default Masuk (fallback)</label>
      <input class="form-control" type="time" name="fallback_checkin" value="<?= e(substr(($base["checkin_time"] ?? "07:00:00"),0,5)) ?>">
    </div>
    <div class="col-12 col-md-4">
      <label class="form-label">Jam Default Pulang (fallback)</label>
      <input class="form-control" type="time" name="fallback_checkout" value="<?= e(substr(($base["checkout_time"] ?? "15:00:00"),0,5)) ?>">
    </div>

    <div class="col-12 mt-2">
      <div class="fw-semibold mb-2"><i class="bi bi-calendar-week me-1"></i>Jadwal per Hari</div>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th style="width:140px;">Hari</th>
              <th style="width:160px;">Hari Kerja</th>
              <th>Jam Masuk</th>
              <th>Jam Pulang</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($days as $d=>$label): 
              $row = $sched_rows[$d] ?? ["is_workday"=>($d<=5?1:0), "checkin_time"=>$base["checkin_time"], "checkout_time"=>$base["checkout_time"]];
            ?>
              <tr>
                <td class="fw-semibold"><?= e($label) ?></td>
                <td>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_workday[<?= $d ?>]" <?= ((int)$row["is_workday"]===1) ? "checked" : "" ?>>
                    <label class="form-check-label small text-secondary"><?= ((int)$row["is_workday"]===1) ? "Kerja" : "Libur" ?></label>
                  </div>
                </td>
                <td>
                  <input class="form-control" type="time" name="checkin_time[<?= $d ?>]" value="<?= e(substr(($row["checkin_time"] ?? $base["checkin_time"]),0,5)) ?>">
                </td>
                <td>
                  <input class="form-control" type="time" name="checkout_time[<?= $d ?>]" value="<?= e(substr(($row["checkout_time"] ?? $base["checkout_time"]),0,5)) ?>">
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-12 d-grid mt-2">
      <button class="btn btn-primary" type="submit"><i class="bi bi-save2 me-1"></i>Simpan Jadwal</button>
    </div>
  </form>

  <hr class="my-4">

  <div class="fw-semibold mb-2"><i class="bi bi-calendar-x me-1"></i>Hari Libur</div>
  <form method="post" class="row g-2">
    <input type="hidden" name="action" value="add_holiday">
    <div class="col-12 col-md-3">
      <label class="form-label">Tanggal</label>
      <input class="form-control" type="date" name="holiday_date" required>
    </div>
    <div class="col-12 col-md-6">
      <label class="form-label">Nama Libur</label>
      <input class="form-control" type="text" name="holiday_name" placeholder="Contoh: Hari Raya / Libur Sekolah" required>
    </div>
    <div class="col-12 col-md-3">
      <label class="form-label">Berlaku</label>
      <select class="form-select" name="holiday_scope">
        <option value="year" selected>Tahun Pelajaran Aktif</option>
        <option value="global">Global (semua tahun)</option>
      </select>
    </div>
    <div class="col-12 d-grid">
      <button class="btn btn-outline-primary" type="submit"><i class="bi bi-plus-circle me-1"></i>Tambah Hari Libur</button>
    </div>
  </form>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th style="width:130px;">Tanggal</th>
          <th>Nama</th>
          <th style="width:140px;">Scope</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($holidays)===0): ?>
          <tr><td colspan="4" class="text-center text-secondary">Belum ada hari libur.</td></tr>
        <?php endif; ?>
        <?php foreach($holidays as $h): ?>
          <tr>
            <td><?= e($h["holiday_date"]) ?></td>
            <td><?= e($h["name"]) ?></td>
            <td class="small text-secondary"><?= $h["academic_year_id"] ? "Tahun aktif" : "Global" ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Hapus hari libur ini?');">
                <input type="hidden" name="action" value="delete_holiday">
                <input type="hidden" name="holiday_id" value="<?= (int)$h["id"] ?>">
                <button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
